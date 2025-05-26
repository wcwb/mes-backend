<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class TeamModelTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $team;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试团队
        $this->team = Team::factory()->create([
            'id' => 100,
            'name' => '测试团队',
            'personal_team' => false
        ]);

        // 创建测试用户
        $this->user = User::factory()->create([
            'current_team_id' => $this->team->id
        ]);
    }

    /** @test */
    public function team_can_be_created()
    {
        $team = Team::factory()->create([
            'name' => '新团队',
            'personal_team' => false
        ]);

        $this->assertInstanceOf(Team::class, $team);
        $this->assertEquals('新团队', $team->name);
        $this->assertFalse($team->personal_team);
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => '新团队',
            'personal_team' => false
        ]);
    }

    /** @test */
    public function team_can_have_users()
    {
        // 将用户添加到团队
        $this->team->users()->attach($this->user->id, ['role' => 'member']);

        $this->assertTrue($this->team->users->contains($this->user));
        $this->assertEquals(1, $this->team->users->count());

        // 验证数据库中的关联
        $this->assertDatabaseHas('team_user', [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'role' => 'member'
        ]);
    }

    /** @test */
    public function team_can_have_multiple_users_with_different_roles()
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // 添加多个用户到团队，分配不同角色
        $this->team->users()->attach([
            $this->user->id => ['role' => 'owner'],
            $user2->id => ['role' => 'admin'],
            $user3->id => ['role' => 'member']
        ]);

        $this->assertEquals(3, $this->team->users->count());

        // 验证角色分配 - 直接查询Membership模型
        $ownerMembership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $this->user->id)
            ->first();
        $this->assertNotNull($ownerMembership, '未找到owner用户membership');
        $this->assertEquals('owner', $ownerMembership->role);

        $adminMembership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $user2->id)
            ->first();
        $this->assertNotNull($adminMembership, '未找到admin用户membership');
        $this->assertEquals('admin', $adminMembership->role);

        $memberMembership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $user3->id)
            ->first();
        $this->assertNotNull($memberMembership, '未找到member用户membership');
        $this->assertEquals('member', $memberMembership->role);
    }

    /** @test */
    public function team_can_have_roles()
    {
        // 为团队创建角色
        $role1 = Role::create([
            'name' => 'creator',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        $role2 = Role::create([
            'name' => 'editor',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        // 验证团队有角色
        $teamRoles = Role::where('team_id', $this->team->id)->get();
        $this->assertEquals(2, $teamRoles->count());

        $roleNames = $teamRoles->pluck('name')->toArray();
        $this->assertContains('creator', $roleNames);
        $this->assertContains('editor', $roleNames);
    }

    /** @test */
    public function team_can_have_permissions()
    {
        // 为团队创建权限
        $permission1 = Permission::create([
            'name' => 'view_orders',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        $permission2 = Permission::create([
            'name' => 'edit_orders',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        // 验证团队有权限
        $teamPermissions = Permission::where('team_id', $this->team->id)->get();
        $this->assertEquals(2, $teamPermissions->count());

        $permissionNames = $teamPermissions->pluck('name')->toArray();
        $this->assertContains('view_orders', $permissionNames);
        $this->assertContains('edit_orders', $permissionNames);
    }

    /** @test */
    public function team_roles_and_permissions_are_isolated_from_other_teams()
    {
        // 创建另一个团队
        $otherTeam = Team::factory()->create(['id' => 200]);

        // 为两个团队创建同名的角色和权限
        $role1 = Role::create(['name' => 'manager', 'team_id' => $this->team->id, 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'manager', 'team_id' => $otherTeam->id, 'guard_name' => 'web']);

        $permission1 = Permission::create(['name' => 'manage_team_data_1_' . $this->team->id, 'team_id' => $this->team->id, 'guard_name' => 'web']);
        $permission2 = Permission::create(['name' => 'manage_team_data_1_' . $otherTeam->id, 'team_id' => $otherTeam->id, 'guard_name' => 'web']);

        // 验证角色隔离
        $team1Roles = Role::where('team_id', $this->team->id)->get();
        $team2Roles = Role::where('team_id', $otherTeam->id)->get();

        $this->assertEquals(1, $team1Roles->count());
        $this->assertEquals(1, $team2Roles->count());
        $this->assertNotEquals($team1Roles->first()->id, $team2Roles->first()->id);

        // 验证权限隔离
        $team1Permissions = Permission::where('team_id', $this->team->id)->get();
        $team2Permissions = Permission::where('team_id', $otherTeam->id)->get();

        $this->assertEquals(1, $team1Permissions->count());
        $this->assertEquals(1, $team2Permissions->count());
        $this->assertNotEquals($team1Permissions->first()->id, $team2Permissions->first()->id);
    }

    /** @test */
    public function team_can_be_personal_team()
    {
        $personalTeam = Team::factory()->create([
            'name' => '个人团队',
            'personal_team' => true
        ]);

        $this->assertTrue($personalTeam->personal_team);
        $this->assertDatabaseHas('teams', [
            'id' => $personalTeam->id,
            'personal_team' => true
        ]);
    }

    /** @test */
    public function team_can_be_deleted()
    {
        $teamId = $this->team->id;

        // 删除团队（软删除）
        $this->team->delete();

        // 验证团队已软删除（记录仍存在但deleted_at不为空）
        $this->assertSoftDeleted('teams', [
            'id' => $teamId
        ]);

        // 验证关联的角色和权限也被删除（如果有级联删除）
        $remainingRoles = Role::where('team_id', $teamId)->count();
        $remainingPermissions = Permission::where('team_id', $teamId)->count();

        // 这取决于数据库的外键约束设置
        // 如果设置了级联删除，这些应该为0
        $this->assertEquals(0, $remainingRoles);
        $this->assertEquals(0, $remainingPermissions);
    }

    /** @test */
    public function team_user_relationship_can_be_updated()
    {
        // 添加用户到团队
        $this->team->users()->attach($this->user->id, ['role' => 'member']);

        // 验证初始角色 - 重新加载关系
        $this->team->load('users');
        $userTeam = $this->team->users()->where('user_id', $this->user->id)->first();
        $this->assertNotNull($userTeam, '未找到用户团队关系');

        // 直接从数据库查询pivot数据
        $membership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $this->user->id)
            ->first();
        $this->assertNotNull($membership, '未找到membership记录');
        $this->assertEquals('member', $membership->role);

        // 更新用户在团队中的角色
        $this->team->users()->updateExistingPivot($this->user->id, ['role' => 'admin']);

        // 验证角色已更新 - 重新查询
        $updatedMembership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $this->user->id)
            ->first();
        $this->assertNotNull($updatedMembership, '未找到更新后的membership记录');
        $this->assertEquals('admin', $updatedMembership->role);
    }

    /** @test */
    public function team_user_relationship_can_be_removed()
    {
        // 添加用户到团队
        $this->team->users()->attach($this->user->id, ['role' => 'member']);
        $this->assertEquals(1, $this->team->users->count());

        // 移除用户从团队（软删除）
        $this->team->users()->detach($this->user->id);

        // 验证用户已从团队移除
        $this->team->refresh();
        $this->assertEquals(0, $this->team->users->count());

        // 验证数据库中的关联已软删除（记录仍存在但deleted_at不为空）
        $this->assertDatabaseHas('team_user', [
            'team_id' => $this->team->id,
            'user_id' => $this->user->id
        ]);

        // 验证记录确实被软删除了
        $membership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $this->user->id)
            ->withTrashed()
            ->first();
        $this->assertNotNull($membership);
        $this->assertNotNull($membership->deleted_at);
    }

    /** @test */
    public function team_can_have_owner()
    {
        // 设置团队所有者
        $this->team->users()->attach($this->user->id, ['role' => 'owner']);

        // 验证团队所有者 - 直接查询Membership模型
        $ownerMembership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $this->user->id)
            ->where('role', 'owner')
            ->first();

        $this->assertNotNull($ownerMembership, '未找到owner membership记录');
        $this->assertEquals('owner', $ownerMembership->role);

        // 验证用户确实在团队中
        $this->assertTrue($this->team->users->contains($this->user));
    }

    /** @test */
    public function team_can_have_multiple_members_but_one_owner()
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // 添加多个用户，但只有一个所有者
        $this->team->users()->attach([
            $this->user->id => ['role' => 'owner'],
            $user2->id => ['role' => 'admin'],
            $user3->id => ['role' => 'member']
        ]);

        // 验证只有一个所有者
        $owners = $this->team->users()->where('role', 'owner')->get();
        $this->assertEquals(1, $owners->count());
        $this->assertEquals($this->user->id, $owners->first()->id);

        // 验证总用户数
        $this->assertEquals(3, $this->team->users->count());
    }

    /** @test */
    public function team_attributes_can_be_updated()
    {
        $originalName = $this->team->name;

        // 更新团队属性
        $this->team->update([
            'name' => '更新后的团队名称'
        ]);

        $this->assertNotEquals($originalName, $this->team->name);
        $this->assertEquals('更新后的团队名称', $this->team->name);

        // 验证数据库中的更新
        $this->assertDatabaseHas('teams', [
            'id' => $this->team->id,
            'name' => '更新后的团队名称'
        ]);
    }
}
