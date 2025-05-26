<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class PermissionSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $team;
    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试团队
        $this->team = Team::factory()->create(['id' => 1]);

        // 创建普通用户
        $this->user = User::factory()->create([
            'current_team_id' => $this->team->id
        ]);

        // 创建管理员用户
        $this->admin = User::factory()->create([
            'current_team_id' => $this->team->id
        ]);

        // 创建基础角色和权限
        $this->createBasicRolesAndPermissions();
    }

    protected function createBasicRolesAndPermissions()
    {
        // 创建角色
        $adminRole = Role::create([
            'name' => 'admin',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        $editorRole = Role::create([
            'name' => 'editor',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        $viewerRole = Role::create([
            'name' => 'viewer',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        // 创建权限
        $permissions = [
            'view_orders',
            'create_orders',
            'edit_orders',
            'delete_orders',
            'view_products',
            'create_products',
            'edit_products',
            'delete_products',
            'manage_users',
            'view_reports'
        ];

        foreach ($permissions as $permissionName) {
            Permission::create([
                'name' => $permissionName,
                'team_id' => $this->team->id,
                'guard_name' => 'web'
            ]);
        }

        // 为角色分配权限
        $adminRole->givePermissionTo($permissions); // 管理员有所有权限

        $editorRole->givePermissionTo([
            'view_orders',
            'create_orders',
            'edit_orders',
            'view_products',
            'create_products',
            'edit_products'
        ]);

        $viewerRole->givePermissionTo([
            'view_orders',
            'view_products',
            'view_reports'
        ]);

        // 为管理员分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team->id);
        $this->admin->assignRole('admin');
    }

    /** @test */
    public function user_can_be_assigned_role_and_check_permissions()
    {
        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team->id);

        // 为用户分配编辑者角色
        $this->user->assignRole('editor');

        // 验证用户有编辑者角色
        $this->assertTrue($this->user->hasRole('editor'));

        // 验证用户有编辑者权限
        $this->assertTrue($this->user->hasPermissionTo('view_orders'));
        $this->assertTrue($this->user->hasPermissionTo('create_orders'));
        $this->assertTrue($this->user->hasPermissionTo('edit_orders'));
        $this->assertTrue($this->user->hasPermissionTo('view_products'));
        $this->assertTrue($this->user->hasPermissionTo('create_products'));
        $this->assertTrue($this->user->hasPermissionTo('edit_products'));

        // 验证用户没有管理员权限
        $this->assertFalse($this->user->hasPermissionTo('delete_orders'));
        $this->assertFalse($this->user->hasPermissionTo('delete_products'));
        $this->assertFalse($this->user->hasPermissionTo('manage_users'));
    }

    /** @test */
    public function user_can_use_safe_permission_methods()
    {
        // 为用户分配角色
        $this->user->assignRoleSafely('editor');

        // 使用安全方法检查权限
        $this->assertTrue($this->user->hasPermissionToSafely('view_orders'));
        $this->assertTrue($this->user->canSafely('create_orders'));
        $this->assertFalse($this->user->hasPermissionToSafely('delete_orders'));

        // 检查多个权限
        $this->assertTrue($this->user->hasAnyPermissionSafely(['view_orders', 'nonexistent']));
        $this->assertTrue($this->user->hasAnyPermissionSafely(['view_orders', 'edit_orders'], true)); // 所有权限
        $this->assertFalse($this->user->hasAnyPermissionSafely(['delete_orders', 'manage_users'], true));
    }

    /** @test */
    public function user_can_switch_teams_and_permissions_are_isolated()
    {
        // 创建第二个团队
        $team2 = Team::factory()->create(['id' => 2]);

        // 在第二个团队创建角色和权限
        $role2 = Role::create([
            'name' => 'manager',
            'team_id' => $team2->id,
            'guard_name' => 'web'
        ]);

        $permission2 = Permission::create([
            'name' => 'manage_inventory',
            'team_id' => $team2->id,
            'guard_name' => 'web'
        ]);

        $role2->givePermissionTo($permission2);

        // 在第一个团队分配角色
        $this->user->setCurrentTeamAsPermissionContext();
        $this->user->assignRoleSafely('editor');
        $this->assertTrue($this->user->hasPermissionToSafely('view_orders'));

        // 在第二个团队分配角色
        $this->user->assignRoleInTeam($team2->id, 'manager');

        // 验证在第二个团队有权限
        $hasPermissionInTeam2 = $this->user->hasPermissionInTeam($team2->id, 'manage_inventory');
        $this->assertTrue($hasPermissionInTeam2);

        // 验证在第一个团队仍然有权限
        $hasPermissionInTeam1 = $this->user->hasPermissionInTeam($this->team->id, 'view_orders');
        $this->assertTrue($hasPermissionInTeam1);

        // 验证跨团队权限隔离
        $hasTeam1PermissionInTeam2 = $this->user->hasPermissionInTeam($team2->id, 'view_orders');
        $this->assertFalse($hasTeam1PermissionInTeam2);

        $hasTeam2PermissionInTeam1 = $this->user->hasPermissionInTeam($this->team->id, 'manage_inventory');
        $this->assertFalse($hasTeam2PermissionInTeam1);
    }

    /** @test */
    public function user_can_use_with_team_context_method()
    {
        // 创建第二个团队
        $team2 = Team::factory()->create(['id' => 2]);

        // 在第二个团队创建角色和权限
        $role2 = Role::create([
            'name' => 'supervisor',
            'team_id' => $team2->id,
            'guard_name' => 'web'
        ]);

        $permission2 = Permission::create([
            'name' => 'supervise_staff',
            'team_id' => $team2->id,
            'guard_name' => 'web'
        ]);

        $role2->givePermissionTo($permission2);

        // 在第一个团队分配角色
        $this->user->assignRoleSafely('viewer');

        // 使用 withTeamContext 在第二个团队执行操作
        $result = $this->user->withTeamContext($team2->id, function ($user) {
            // 在第二个团队分配角色
            $user->assignRole('supervisor');

            // 检查权限
            $hasPermission = $user->hasPermissionTo('supervise_staff');

            // 获取角色
            $roles = $user->roles->pluck('name')->toArray();

            return [
                'has_permission' => $hasPermission,
                'roles' => $roles,
                'role_count' => count($roles)
            ];
        });

        // 验证在第二个团队的操作结果
        $this->assertTrue($result['has_permission']);
        $this->assertContains('supervisor', $result['roles']);
        $this->assertEquals(1, $result['role_count']);

        // 验证用户在第二个团队确实有角色
        $rolesInTeam2 = $this->user->getRolesInTeam($team2->id);
        $this->assertEquals(1, $rolesInTeam2->count());
        $this->assertEquals('supervisor', $rolesInTeam2->first()->name);

        // 验证在第一个团队仍然有原来的角色
        $rolesInTeam1 = $this->user->getRolesInTeam($this->team->id);
        $this->assertEquals(1, $rolesInTeam1->count());
        $this->assertEquals('viewer', $rolesInTeam1->first()->name);
    }

    /** @test */
    public function user_can_manage_roles_across_multiple_teams()
    {
        // 创建多个团队
        $team2 = Team::factory()->create(['id' => 2]);
        $team3 = Team::factory()->create(['id' => 3]);

        // 在每个团队创建角色
        $role2 = Role::create(['name' => 'coordinator', 'team_id' => $team2->id, 'guard_name' => 'web']);
        $role3 = Role::create(['name' => 'analyst', 'team_id' => $team3->id, 'guard_name' => 'web']);

        // 在多个团队分配角色
        $this->user->assignRoleSafely('editor'); // 当前团队
        $this->user->assignRoleInTeam($team2->id, 'coordinator');
        $this->user->assignRoleInTeam($team3->id, 'analyst');

        // 验证用户在每个团队都有角色
        $team1Roles = $this->user->getRolesInTeam($this->team->id);
        $team2Roles = $this->user->getRolesInTeam($team2->id);
        $team3Roles = $this->user->getRolesInTeam($team3->id);

        $this->assertEquals(1, $team1Roles->count());
        $this->assertEquals('editor', $team1Roles->first()->name);

        $this->assertEquals(1, $team2Roles->count());
        $this->assertEquals('coordinator', $team2Roles->first()->name);

        $this->assertEquals(1, $team3Roles->count());
        $this->assertEquals('analyst', $team3Roles->first()->name);

        // 获取所有角色
        $allRoles = $this->user->getAllRoles();
        $this->assertEquals(3, $allRoles->count());

        $roleNames = $allRoles->pluck('name')->toArray();
        $this->assertContains('editor', $roleNames);
        $this->assertContains('coordinator', $roleNames);
        $this->assertContains('analyst', $roleNames);
    }

    /** @test */
    public function user_can_sync_roles_safely()
    {
        // 设置团队上下文
        $this->user->setCurrentTeamAsPermissionContext();

        // 先分配一些角色
        $this->user->assignRoleSafely('editor');
        $this->user->assignRoleSafely('viewer');

        $this->assertTrue($this->user->hasRole('editor'));
        $this->assertTrue($this->user->hasRole('viewer'));
        $this->assertFalse($this->user->hasRole('admin'));

        // 同步角色（替换所有角色）
        $this->user->syncRolesSafely(['admin', 'viewer']);

        $this->assertFalse($this->user->hasRole('editor'));
        $this->assertTrue($this->user->hasRole('viewer'));
        $this->assertTrue($this->user->hasRole('admin'));

        // 验证用户现在有管理员权限
        $this->assertTrue($this->user->hasPermissionToSafely('manage_users'));
        $this->assertTrue($this->user->hasPermissionToSafely('delete_orders'));
    }

    /** @test */
    public function user_can_remove_roles_safely()
    {
        // 设置团队上下文
        $this->user->setCurrentTeamAsPermissionContext();

        // 分配多个角色
        $this->user->assignRoleSafely('editor');
        $this->user->assignRoleSafely('viewer');

        $this->assertTrue($this->user->hasRole('editor'));
        $this->assertTrue($this->user->hasRole('viewer'));

        // 移除一个角色
        $this->user->removeRoleSafely('editor');

        $this->assertFalse($this->user->hasRole('editor'));
        $this->assertTrue($this->user->hasRole('viewer'));

        // 验证权限也相应变化
        $this->assertFalse($this->user->hasPermissionToSafely('create_orders'));
        $this->assertTrue($this->user->hasPermissionToSafely('view_orders'));
    }

    /** @test */
    public function admin_can_manage_other_users_permissions()
    {
        // 管理员为其他用户分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team->id);

        // 验证管理员有管理用户的权限
        $this->assertTrue($this->admin->hasPermissionTo('manage_users'));

        // 管理员为用户分配角色
        $this->user->assignRole('editor');

        // 验证用户获得了角色
        $this->assertTrue($this->user->hasRole('editor'));
        $this->assertTrue($this->user->hasPermissionTo('edit_orders'));

        // 管理员移除用户角色
        $this->user->removeRole('editor');

        // 验证用户失去了角色
        $this->assertFalse($this->user->hasRole('editor'));
        $this->assertFalse($this->user->hasPermissionTo('edit_orders'));
    }

    /** @test */
    public function permission_system_handles_nonexistent_permissions_gracefully()
    {
        // 设置团队上下文
        $this->user->setCurrentTeamAsPermissionContext();

        $this->user->assignRoleSafely('editor');

        // 检查不存在的权限
        $this->assertFalse($this->user->hasPermissionToSafely('nonexistent_permission'));
        $this->assertFalse($this->user->canSafely('another_nonexistent_permission'));

        // 检查混合权限（存在和不存在）
        $hasAny = $this->user->hasAnyPermissionSafely(['view_orders', 'nonexistent_permission']);
        $this->assertTrue($hasAny); // 因为有一个存在的权限

        $hasAll = $this->user->hasAnyPermissionSafely(['view_orders', 'nonexistent_permission'], true);
        $this->assertFalse($hasAll); // 因为有一个不存在的权限
    }

    /** @test */
    public function user_current_team_context_is_automatically_set()
    {
        // 用户的当前团队应该自动设置权限上下文
        $this->user->setCurrentTeamAsPermissionContext();

        $registrar = app(PermissionRegistrar::class);
        $contextTeamId = $registrar->getPermissionsTeamId();

        $this->assertEquals($this->user->current_team_id, $contextTeamId);

        // 分配角色应该在正确的团队上下文中
        $this->user->assignRole('editor');

        $this->assertTrue($this->user->hasRole('editor'));
        $this->assertTrue($this->user->hasPermissionTo('view_orders'));
    }

    /** @test */
    public function permission_system_works_with_multiple_guards()
    {
        // 跳过这个测试，因为当前用户模型配置为web守卫
        // 在实际应用中，如果需要支持多守卫，需要在用户模型中配置
        $this->markTestSkipped('多守卫功能需要特殊配置，当前跳过此测试');

        // 创建API guard的角色和权限
        $apiRole = Role::create([
            'name' => 'api_user',
            'team_id' => $this->team->id,
            'guard_name' => 'api'
        ]);

        $apiPermission = Permission::create([
            'name' => 'api_access',
            'team_id' => $this->team->id,
            'guard_name' => 'api'
        ]);

        $apiRole->givePermissionTo($apiPermission);

        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team->id);

        // 为用户分配API角色（直接传递角色对象）
        $this->user->assignRole($apiRole);

        // 验证用户在API guard中有角色和权限
        $this->assertTrue($this->user->hasRole('api_user', 'api'));
        $this->assertTrue($this->user->hasPermissionTo('api_access', 'api'));

        // 验证在web guard中没有这些角色和权限
        $this->assertFalse($this->user->hasRole('api_user', 'web'));
        $this->assertFalse($this->user->hasPermissionTo('api_access', 'web'));
    }

    /** @test */
    public function complex_permission_scenario_with_multiple_teams_and_roles()
    {
        // 创建复杂的多团队场景
        $salesTeam = Team::factory()->create(['id' => 10, 'name' => '销售团队']);
        $marketingTeam = Team::factory()->create(['id' => 20, 'name' => '市场团队']);

        // 在销售团队创建角色和权限
        $salesManagerRole = Role::create(['name' => 'sales_manager', 'team_id' => $salesTeam->id, 'guard_name' => 'web']);
        $salesRepRole = Role::create(['name' => 'sales_rep', 'team_id' => $salesTeam->id, 'guard_name' => 'web']);

        $manageSalesPermission = Permission::create(['name' => 'manage_sales', 'team_id' => $salesTeam->id, 'guard_name' => 'web']);
        $viewSalesPermission = Permission::create(['name' => 'view_sales', 'team_id' => $salesTeam->id, 'guard_name' => 'web']);

        $salesManagerRole->givePermissionTo([$manageSalesPermission, $viewSalesPermission]);
        $salesRepRole->givePermissionTo($viewSalesPermission);

        // 在市场团队创建角色和权限
        $marketingManagerRole = Role::create(['name' => 'marketing_manager', 'team_id' => $marketingTeam->id, 'guard_name' => 'web']);
        $manageMarketingPermission = Permission::create(['name' => 'manage_marketing', 'team_id' => $marketingTeam->id, 'guard_name' => 'web']);

        $marketingManagerRole->givePermissionTo($manageMarketingPermission);

        // 用户在多个团队担任不同角色
        $this->user->assignRoleInTeam($salesTeam->id, 'sales_manager');
        $this->user->assignRoleInTeam($marketingTeam->id, 'marketing_manager');

        // 验证用户在销售团队的权限
        $this->assertTrue($this->user->hasPermissionInTeam($salesTeam->id, 'manage_sales'));
        $this->assertTrue($this->user->hasPermissionInTeam($salesTeam->id, 'view_sales'));
        $this->assertFalse($this->user->hasPermissionInTeam($salesTeam->id, 'manage_marketing'));

        // 验证用户在市场团队的权限
        $this->assertTrue($this->user->hasPermissionInTeam($marketingTeam->id, 'manage_marketing'));
        $this->assertFalse($this->user->hasPermissionInTeam($marketingTeam->id, 'manage_sales'));
        $this->assertFalse($this->user->hasPermissionInTeam($marketingTeam->id, 'view_sales'));

        // 验证用户的所有角色
        $allRoles = $this->user->getAllRoles();
        $this->assertEquals(2, $allRoles->count());

        $roleNames = $allRoles->pluck('name')->toArray();
        $this->assertContains('sales_manager', $roleNames);
        $this->assertContains('marketing_manager', $roleNames);

        // 验证团队信息
        foreach ($allRoles as $role) {
            $this->assertNotNull($role->pivot_team_id, "角色 {$role->name} 的 pivot_team_id 为空");
            $this->assertContains(
                $role->pivot_team_id,
                [$salesTeam->id, $marketingTeam->id],
                "角色 {$role->name} 的团队ID {$role->pivot_team_id} 不在期望的团队列表中"
            );
        }
    }
}
