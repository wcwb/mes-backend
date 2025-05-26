<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class UserModelTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $team;
    protected $user;
    protected $role;
    protected $permission;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试团队
        $this->team = Team::factory()->create(['id' => 10]);

        // 创建测试用户
        $this->user = User::factory()->create([
            'current_team_id' => $this->team->id
        ]);

        // 创建测试角色和权限
        $this->role = Role::create([
            'name' => 'creator',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        $this->permission = Permission::create([
            'name' => 'view_orders',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        // 为角色分配权限
        $this->role->givePermissionTo($this->permission);
    }

    /** @test */
    public function user_can_be_created_with_current_team()
    {
        $user = User::factory()->create([
            'current_team_id' => $this->team->id
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($this->team->id, $user->current_team_id);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'current_team_id' => $this->team->id
        ]);
    }

    /** @test */
    public function user_can_set_current_team_as_permission_context()
    {
        $this->user->setCurrentTeamAsPermissionContext();

        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $contextTeamId = $registrar->getPermissionsTeamId();

        $this->assertEquals($this->user->current_team_id, $contextTeamId);
    }

    /** @test */
    public function user_can_assign_role_safely()
    {
        $this->user->assignRoleSafely('creator');

        $this->assertTrue($this->user->hasRole('creator'));

        // 验证角色分配到正确的团队
        $userRole = $this->user->roles->first();
        $this->assertEquals($this->team->id, $userRole->team_id);
    }

    /** @test */
    public function user_can_assign_role_in_specific_team()
    {
        // 创建另一个团队和角色
        $otherTeam = Team::factory()->create(['id' => 20]);
        $otherRole = Role::create([
            'name' => 'viewer',
            'team_id' => $otherTeam->id,
            'guard_name' => 'web'
        ]);

        $this->user->assignRoleInTeam($otherTeam->id, 'viewer');

        // 验证用户在指定团队有角色
        $rolesInTeam = $this->user->getRolesInTeam($otherTeam->id);
        $this->assertEquals(1, $rolesInTeam->count());
        $this->assertEquals('viewer', $rolesInTeam->first()->name);
    }

    /** @test */
    public function user_can_check_permission_safely()
    {
        $this->user->assignRoleSafely('creator');

        $this->assertTrue($this->user->hasPermissionToSafely('view_orders'));
        $this->assertTrue($this->user->canSafely('view_orders'));
    }

    /** @test */
    public function user_can_check_permission_in_specific_team()
    {
        $this->user->assignRoleSafely('creator');

        $hasPermission = $this->user->hasPermissionInTeam($this->team->id, 'view_orders');
        $this->assertTrue($hasPermission);

        // 在其他团队应该没有权限
        $otherTeam = Team::factory()->create(['id' => 30]);
        $hasPermissionInOtherTeam = $this->user->hasPermissionInTeam($otherTeam->id, 'view_orders');
        $this->assertFalse($hasPermissionInOtherTeam);
    }

    /** @test */
    public function user_can_check_any_permission_safely()
    {
        $this->user->assignRoleSafely('creator');

        $permissions = ['view_orders', 'edit_orders', 'nonexistent_permission'];

        // 检查是否有任意一个权限
        $hasAny = $this->user->hasAnyPermissionSafely($permissions);
        $this->assertTrue($hasAny);

        // 检查是否有所有权限
        $hasAll = $this->user->hasAnyPermissionSafely($permissions, true);
        $this->assertFalse($hasAll); // 因为有不存在的权限

        // 只检查存在的权限
        $existingPermissions = ['view_orders'];
        $hasAllExisting = $this->user->hasAnyPermissionSafely($existingPermissions, true);
        $this->assertTrue($hasAllExisting);
    }

    /** @test */
    public function user_can_remove_role_safely()
    {
        $this->user->assignRoleSafely('creator');
        $this->assertTrue($this->user->hasRole('creator'));

        $this->user->removeRoleSafely('creator');
        $this->assertFalse($this->user->hasRole('creator'));
    }

    /** @test */
    public function user_can_sync_roles_safely()
    {
        // 创建多个角色
        $editorRole = Role::create([
            'name' => 'editor',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        $approverRole = Role::create([
            'name' => 'approver',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        // 先分配一个角色
        $this->user->assignRoleSafely('creator');
        $this->assertTrue($this->user->hasRole('creator'));

        // 同步角色（替换所有角色）
        $this->user->syncRolesSafely(['editor', 'approver']);

        $this->assertFalse($this->user->hasRole('creator'));
        $this->assertTrue($this->user->hasRole('editor'));
        $this->assertTrue($this->user->hasRole('approver'));
    }

    /** @test */
    public function user_can_get_roles_in_team()
    {
        // 在当前团队分配角色
        $this->user->assignRoleSafely('creator');

        // 在其他团队分配角色
        $otherTeam = Team::factory()->create(['id' => 40]);
        $otherRole = Role::create([
            'name' => 'viewer',
            'team_id' => $otherTeam->id,
            'guard_name' => 'web'
        ]);
        $this->user->assignRoleInTeam($otherTeam->id, 'viewer');

        // 获取当前团队的角色
        $currentTeamRoles = $this->user->getRolesInTeam($this->team->id);
        $this->assertEquals(1, $currentTeamRoles->count());
        $this->assertEquals('creator', $currentTeamRoles->first()->name);

        // 获取其他团队的角色
        $otherTeamRoles = $this->user->getRolesInTeam($otherTeam->id);
        $this->assertEquals(1, $otherTeamRoles->count());

        // 调试信息：检查实际获取到的角色名称
        $actualRoleName = $otherTeamRoles->first()->name;
        $this->assertEquals('viewer', $actualRoleName, "期望获取到'viewer'角色，但实际获取到'{$actualRoleName}'角色");
    }

    /** @test */
    public function user_can_get_all_roles()
    {
        // 在多个团队分配角色
        $this->user->assignRoleSafely('creator');

        $otherTeam = Team::factory()->create(['id' => 50]);
        $otherRole = Role::create([
            'name' => 'viewer',
            'team_id' => $otherTeam->id,
            'guard_name' => 'web'
        ]);
        $this->user->assignRoleInTeam($otherTeam->id, 'viewer');

        // 获取所有角色
        $allRoles = $this->user->getAllRoles();
        $this->assertEquals(2, $allRoles->count());

        // 验证角色包含团队信息
        $roleNames = $allRoles->pluck('name')->toArray();
        $this->assertContains('creator', $roleNames);
        $this->assertContains('viewer', $roleNames);

        // 验证pivot_team_id字段
        foreach ($allRoles as $role) {
            $this->assertNotNull($role->pivot_team_id);
        }
    }

    /** @test */
    public function user_can_execute_operations_with_team_context()
    {
        // 创建另一个团队和角色
        $otherTeam = Team::factory()->create(['id' => 60]);
        $otherRole = Role::create([
            'name' => 'editor',
            'team_id' => $otherTeam->id,
            'guard_name' => 'web'
        ]);

        $otherPermission = Permission::create([
            'name' => 'edit_products',
            'team_id' => $otherTeam->id,
            'guard_name' => 'web'
        ]);

        $otherRole->givePermissionTo($otherPermission);

        // 在指定团队上下文中执行操作
        $result = $this->user->withTeamContext($otherTeam->id, function ($user) {
            // 分配角色
            $user->assignRole('editor');

            // 检查权限
            $hasPermission = $user->hasPermissionToSafely('edit_products');

            // 获取角色
            $roles = $user->roles->pluck('name')->toArray();

            return [
                'has_permission' => $hasPermission,
                'roles' => $roles
            ];
        });

        $this->assertTrue($result['has_permission']);
        $this->assertContains('editor', $result['roles']);

        // 验证用户在其他团队确实有角色
        $rolesInOtherTeam = $this->user->getRolesInTeam($otherTeam->id);
        $this->assertEquals(1, $rolesInOtherTeam->count());
        $this->assertEquals('editor', $rolesInOtherTeam->first()->name);
    }

    /** @test */
    public function user_permission_context_is_isolated_between_teams()
    {
        // 创建两个团队，每个团队都有同名的权限
        $team1 = Team::factory()->create(['id' => 70]);
        $team2 = Team::factory()->create(['id' => 80]);

        $role1 = Role::create(['name' => 'manager', 'team_id' => $team1->id, 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'manager', 'team_id' => $team2->id, 'guard_name' => 'web']);

        $permission1 = Permission::create(['name' => 'manage_team_data_' . $team1->id, 'team_id' => $team1->id, 'guard_name' => 'web']);
        $permission2 = Permission::create(['name' => 'manage_team_data_' . $team2->id, 'team_id' => $team2->id, 'guard_name' => 'web']);

        $role1->givePermissionTo($permission1);
        // 注意：role2 没有分配 permission2

        // 在团队1分配角色
        $this->user->assignRoleInTeam($team1->id, 'manager');

        // 在团队2分配角色
        $this->user->assignRoleInTeam($team2->id, 'manager');

        // 在团队1上下文中应该有权限
        $hasPermissionInTeam1 = $this->user->hasPermissionInTeam($team1->id, 'manage_team_data_' . $team1->id);
        $this->assertTrue($hasPermissionInTeam1);

        // 在团队2上下文中应该没有权限（因为role2没有分配permission2）
        $hasPermissionInTeam2 = $this->user->hasPermissionInTeam($team2->id, 'manage_team_data_' . $team2->id);
        $this->assertFalse($hasPermissionInTeam2);
    }

    /** @test */
    public function user_current_team_id_affects_default_permission_context()
    {
        // 分配角色到当前团队
        $this->user->assignRoleSafely('creator');

        // 设置团队上下文
        $this->user->setCurrentTeamAsPermissionContext();

        // 检查权限应该成功
        $this->assertTrue($this->user->hasPermissionToSafely('view_orders'));

        // 更改用户的当前团队
        $newTeam = Team::factory()->create(['id' => 90]);
        $this->user->current_team_id = $newTeam->id;
        $this->user->save();

        // 重新设置团队上下文
        $this->user->setCurrentTeamAsPermissionContext();

        // 现在检查原来的权限应该失败（因为在新团队中没有角色）
        $this->assertFalse($this->user->hasPermissionToSafely('view_orders'));
    }
}
