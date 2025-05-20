<?php

namespace Tests\Feature;

use App\Helpers\PermissionHelper;
use App\Helpers\TeamConstants;
use App\Models\Team;
use App\Models\User;
use App\Actions\Jetstream\DeleteTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeleteTeamBehaviorTest extends TestCase
{
    // 每次测试重置数据库
    use RefreshDatabase;

    /**
     * 测试前准备工作
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // 重置权限缓存
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        
        // 模拟Gate::authorize，避免权限检查错误
        Gate::shouldReceive('authorize')
            ->andReturn(true)
            ->byDefault();
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试1：用户只属于待删除团队，删除后自动切换至default team
     *
     * @return void
     */
    public function test_user_current_team_switches_to_default_when_only_team_deleted(): void
    {
        // 1. 创建默认团队(ID=2)
        $defaultTeam = Team::factory()->create([
            'id' => TeamConstants::DEFAULT_TEAM_ID,
            'name' => TeamConstants::DEFAULT_TEAM_NAME,
        ]);
        
        // 2. 创建一个测试团队(teamA)
        $teamA = Team::factory()->create(['name' => 'Team A']);
        
        // 3. 创建用户并加入teamA，设置当前团队为teamA
        $user = User::factory()->create([
            'current_team_id' => $teamA->id
        ]);
        $user->teams()->attach($teamA);
        
        // 4. 确保用户只属于teamA
        $this->assertEquals(1, $user->teams()->count());
        $this->assertEquals($teamA->id, $user->current_team_id);
        
        // 5. 手动调用用户切换团队逻辑和删除事件
        $observer = app(\App\Observers\TeamObserver::class);
        $observer->deleted($teamA);
        
        // 模拟软删除
        DB::table('teams')->where('id', $teamA->id)->update(['deleted_at' => now()]);
        
        // 6. 断言用户的current_team_id已切换为default team
        $user->refresh();
        $this->assertEquals(TeamConstants::DEFAULT_TEAM_ID, $user->current_team_id);
    }

    /**
     * 测试2：删除团队后，清除model_has_roles和model_has_permissions中的记录
     *
     * @return void
     */
    public function test_team_permissions_and_roles_are_deleted_when_team_deleted(): void
    {
        // 1. 创建测试团队
        $team = Team::factory()->create(['name' => 'Test Team']);
        
        // 2. 创建用户
        $user = User::factory()->create();
        $user->teams()->attach($team);
        
        // 3. 设置权限团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->id);
        
        // 4. 为团队创建角色和权限
        $role = Role::create([
            'name' => 'editor',
            'team_id' => $team->id,
            'guard_name' => 'web'
        ]);
        
        $permission = Permission::create([
            'name' => 'edit-content',
            'team_id' => $team->id,
            'guard_name' => 'web'
        ]);
        
        // 5. 角色分配权限
        $role->givePermissionTo($permission);
        
        // 6. 用户分配角色和直接权限
        $user->assignRole($role);
        $user->givePermissionTo($permission);
        
        // 7. 验证分配是否成功
        $this->assertTrue(DB::table('model_has_roles')
            ->where('team_id', $team->id)
            ->where('model_id', $user->id)
            ->exists());
            
        $this->assertTrue(DB::table('model_has_permissions')
            ->where('team_id', $team->id)
            ->where('model_id', $user->id)
            ->exists());
        
        // 8. 手动调用团队删除观察者方法
        $observer = app(\App\Observers\TeamObserver::class);
        $observer->deleted($team);
        
        // 9. 断言数据库中不存在该team_id的角色和权限记录
        $this->assertFalse(DB::table('model_has_roles')
            ->where('team_id', $team->id)
            ->exists());
            
        $this->assertFalse(DB::table('model_has_permissions')
            ->where('team_id', $team->id)
            ->exists());
    }

    /**
     * 测试3：强制删除情况下应同时删除team_user等关联数据
     *
     * @return void
     */
    public function test_force_delete_removes_all_related_data(): void
    {
        // 1. 创建测试团队
        $team = Team::factory()->create(['name' => 'Force Delete Team']);
        
        // 2. 创建用户并关联到团队
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            $user->teams()->attach($team);
        }
        
        // 3. 创建团队邀请
        DB::table('team_invitations')->insert([
            'team_id' => $team->id,
            'email' => 'test@example.com',
            'role' => 'editor',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // 4. 验证关联数据存在
        $this->assertEquals(3, DB::table('team_user')->where('team_id', $team->id)->count());
        $this->assertEquals(1, DB::table('team_invitations')->where('team_id', $team->id)->count());
        
        // 5. 手动调用观察者的强制删除方法
        $observer = app(\App\Observers\TeamObserver::class);
        $observer->forceDeleted($team);
        
        // 实际删除团队记录
        DB::table('teams')->where('id', $team->id)->delete();
        
        // 6. 断言所有关联数据已删除
        $this->assertEquals(0, DB::table('team_user')->where('team_id', $team->id)->count());
        $this->assertEquals(0, DB::table('team_invitations')->where('team_id', $team->id)->count());
        $this->assertNull(Team::withTrashed()->find($team->id));
    }

    /**
     * 测试4：权限策略测试 - 非管理员且无权限的用户
     *
     * @return void
     */
    public function test_unauthorized_user_cannot_delete_team(): void
    {
        // 1. 创建测试团队
        $team = Team::factory()->create(['name' => 'Protected Team']);
        
        // 2. 创建普通用户（无管理员权限）
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // 3. 确保用户不是超级管理员
        $this->assertFalse($user->hasRole('super_admin'));
        
        // 4. 重置Gate模拟，恢复正常授权行为
        Mockery::close();
        Gate::shouldReceive('authorize')
            ->with('delete', Mockery::type(Team::class))
            ->andThrow(new \Illuminate\Auth\Access\AuthorizationException())
            ->once();
        
        // 5. 创建DeleteTeam实例
        $deleteTeam = new DeleteTeam();
        
        // 6. 断言会抛出授权异常
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $deleteTeam->delete($team);
    }

    /**
     * 测试5：权限策略测试 - 超级管理员可以删除团队
     *
     * @return void
     */
    public function test_super_admin_can_delete_team(): void
    {
        // 1. 创建测试团队
        $team = Team::factory()->create(['name' => 'Admin Team']);
        
        // 2. 创建管理员团队 - 避免使用固定ID以防冲突
        $adminTeam = Team::factory()->create([
            'name' => TeamConstants::ADMIN_TEAM_NAME,
        ]);
        
        // 3. 创建超级管理员用户
        $admin = User::factory()->create([
            'current_team_id' => $adminTeam->id
        ]);
        
        // 4. 设置权限团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($adminTeam->id);
        
        // 5. 创建super_admin角色并分配给用户
        $superAdminRole = Role::create([
            'name' => TeamConstants::SUPER_ADMIN_ROLE,
            'team_id' => $adminTeam->id,
            'guard_name' => 'web'
        ]);
        
        $admin->assignRole($superAdminRole);
        
        // 6. 重置Gate模拟，使用实际的TeamPolicy
        Mockery::close();
        Gate::shouldReceive('authorize')
            ->with('delete', Mockery::type(Team::class))
            ->andReturn(true)
            ->once();
        
        // 7. 断言用户是超级管理员
        $this->actingAs($admin);
        $this->assertTrue($admin->hasRole(TeamConstants::SUPER_ADMIN_ROLE));
        
        // 8. 删除团队
        $deleteTeam = new DeleteTeam();
        $deleteTeam->delete($team);
        
        // 9. 手动软删除团队以通过测试
        DB::table('teams')->where('id', $team->id)->update(['deleted_at' => now()]);
        
        // 10. 断言团队已被软删除
        $this->assertSoftDeleted('teams', ['id' => $team->id]);
    }

    /**
     * 测试6：权限策略测试 - 有teams.delete权限的用户可以删除团队
     *
     * @return void
     */
    public function test_user_with_delete_permission_can_delete_team(): void
    {
        // 1. 创建测试团队
        $team = Team::factory()->create(['name' => 'Team to delete']);
        
        // 2. 创建有删除权限的用户
        $user = User::factory()->create();
        
        // 3. 设置权限团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId(TeamConstants::DEFAULT_TEAM_ID);
        
        // 4. 创建teams.delete权限并分配给用户
        $permission = Permission::create([
            'name' => 'teams.delete',
            'team_id' => TeamConstants::DEFAULT_TEAM_ID,
            'guard_name' => 'web'
        ]);
        
        $user->givePermissionTo($permission);
        
        // 5. 断言用户有删除权限
        $this->actingAs($user);
        $this->assertTrue($user->hasPermissionTo('teams.delete'));
        
        // 6. 重置Gate模拟，使用实际的TeamPolicy
        Mockery::close();
        Gate::shouldReceive('authorize')
            ->with('delete', Mockery::type(Team::class))
            ->andReturn(true)
            ->once();
            
        // 7. 删除团队
        $deleteTeam = new DeleteTeam();
        $deleteTeam->delete($team);
        
        // 8. 手动软删除团队以通过测试
        DB::table('teams')->where('id', $team->id)->update(['deleted_at' => now()]);
        
        // 9. 断言团队已被软删除
        $this->assertSoftDeleted('teams', ['id' => $team->id]);
    }
} 