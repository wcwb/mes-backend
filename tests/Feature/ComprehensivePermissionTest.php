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

/**
 * 全面的权限系统测试
 * 
 * 这个测试类验证权限与团队管理系统文档中描述的所有功能，
 * 确保系统按照文档规范正常工作。
 */
class ComprehensivePermissionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $team1;
    protected $team2;
    protected $team3;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试团队
        $this->team1 = Team::factory()->create(['id' => 100, 'name' => '开发团队']);
        $this->team2 = Team::factory()->create(['id' => 200, 'name' => '测试团队']);
        $this->team3 = Team::factory()->create(['id' => 300, 'name' => '运维团队']);

        // 创建测试用户
        $this->user = User::factory()->create(['current_team_id' => $this->team1->id]);

        // 设置用户在团队中的成员关系
        $this->team1->users()->attach($this->user->id, ['role' => 'developer']);
        $this->team2->users()->attach($this->user->id, ['role' => 'tester']);
        $this->team3->users()->attach($this->user->id, ['role' => 'admin']);

        $this->createTestPermissionsAndRoles();
    }

    protected function createTestPermissionsAndRoles()
    {
        $teams = [$this->team1, $this->team2, $this->team3];

        foreach ($teams as $team) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($team->id);

            // 创建权限（使用唯一名称避免冲突）
            $permissions = [
                'comp_view_dashboard',
                'comp_create_projects',
                'comp_edit_projects',
                'comp_delete_projects',
                'comp_manage_users',
                'comp_view_reports',
                'comp_export_data',
                'comp_system_admin'
            ];

            foreach ($permissions as $permissionName) {
                Permission::create([
                    'name' => $permissionName,
                    'team_id' => $team->id,
                    'guard_name' => 'web'
                ]);
            }

            // 创建角色并分配权限
            $developerRole = Role::create([
                'name' => 'developer',
                'team_id' => $team->id,
                'guard_name' => 'web'
            ]);
            $developerRole->givePermissionTo(['view_dashboard', 'create_projects', 'edit_projects']);

            $testerRole = Role::create([
                'name' => 'tester',
                'team_id' => $team->id,
                'guard_name' => 'web'
            ]);
            $testerRole->givePermissionTo(['view_dashboard', 'view_reports']);

            $adminRole = Role::create([
                'name' => 'admin',
                'team_id' => $team->id,
                'guard_name' => 'web'
            ]);
            $adminRole->givePermissionTo([
                'view_dashboard',
                'create_projects',
                'edit_projects',
                'delete_projects',
                'manage_users',
                'view_reports',
                'export_data',
                'system_admin'
            ]);
        }
    }

    /** @test */
    public function test_hasPermissionToSafely_with_string_permission()
    {
        // 在团队1分配developer角色
        $this->user->assignRoleInTeam($this->team1->id, 'developer');

        // 测试字符串权限检查
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'view_dashboard'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'create_projects'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'edit_projects'));
        $this->assertFalse($this->user->hasPermissionInTeam($this->team1->id, 'delete_projects'));
        $this->assertFalse($this->user->hasPermissionInTeam($this->team1->id, 'manage_users'));
    }

    /** @test */
    public function test_hasPermissionToSafely_with_permission_object()
    {
        // 在团队1分配developer角色
        $this->user->assignRoleInTeam($this->team1->id, 'developer');

        // 获取权限对象
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $viewPermission = Permission::findByName('view_dashboard');
        $deletePermission = Permission::findByName('delete_projects');

        // 测试权限对象检查
        $this->assertTrue($this->user->hasPermissionToSafely($viewPermission));
        $this->assertFalse($this->user->hasPermissionToSafely($deletePermission));
    }

    /** @test */
    public function test_canSafely_method()
    {
        // 在团队1分配developer角色
        $this->user->assignRoleInTeam($this->team1->id, 'developer');
        $this->user->setCurrentTeamAsPermissionContext();

        // 测试canSafely方法
        $this->assertTrue($this->user->canSafely('view_dashboard'));
        $this->assertTrue($this->user->canSafely('create_projects'));
        $this->assertFalse($this->user->canSafely('delete_projects'));
        $this->assertFalse($this->user->canSafely('nonexistent_permission'));
    }

    /** @test */
    public function test_hasAnyPermissionSafely_method()
    {
        // 在团队1分配developer角色
        $this->user->assignRoleInTeam($this->team1->id, 'developer');
        $this->user->setCurrentTeamAsPermissionContext();

        $permissions = ['view_dashboard', 'create_projects', 'delete_projects', 'manage_users'];

        // 测试任意权限检查（默认行为）
        $hasAny = $this->user->hasAnyPermissionSafely($permissions);
        $this->assertTrue($hasAny, '用户应该至少有一个权限');

        // 测试所有权限检查
        $hasAll = $this->user->hasAnyPermissionSafely($permissions, true);
        $this->assertFalse($hasAll, '用户不应该拥有所有权限');

        // 测试只有拥有的权限
        $ownedPermissions = ['view_dashboard', 'create_projects', 'edit_projects'];
        $hasAllOwned = $this->user->hasAnyPermissionSafely($ownedPermissions, true);
        $this->assertTrue($hasAllOwned, '用户应该拥有所有这些权限');

        // 测试空数组
        $hasEmpty = $this->user->hasAnyPermissionSafely([]);
        $this->assertTrue($hasEmpty, '空权限数组应该返回true');
    }

    /** @test */
    public function test_assignRoleSafely_method()
    {
        // 设置当前团队上下文
        $this->user->setCurrentTeamAsPermissionContext();

        // 测试分配单个角色
        $this->user->assignRoleSafely('developer');
        $this->assertTrue($this->user->hasRole('developer'));
        $this->assertTrue($this->user->hasPermissionToSafely('view_dashboard'));

        // 测试分配多个角色
        $this->user->assignRoleSafely(['tester', 'admin']);
        $this->assertTrue($this->user->hasRole('developer'));
        $this->assertTrue($this->user->hasRole('tester'));
        $this->assertTrue($this->user->hasRole('admin'));

        // 验证权限累积
        $this->assertTrue($this->user->hasPermissionToSafely('view_dashboard'));
        $this->assertTrue($this->user->hasPermissionToSafely('create_projects'));
        $this->assertTrue($this->user->hasPermissionToSafely('view_reports'));
        $this->assertTrue($this->user->hasPermissionToSafely('manage_users'));
    }

    /** @test */
    public function test_assignRoleInTeam_method()
    {
        // 在不同团队分配不同角色
        $this->user->assignRoleInTeam($this->team1->id, 'developer');
        $this->user->assignRoleInTeam($this->team2->id, 'tester');
        $this->user->assignRoleInTeam($this->team3->id, 'admin');

        // 验证团队1的角色
        $team1Roles = $this->user->getRolesInTeam($this->team1->id);
        $this->assertEquals(1, $team1Roles->count());
        $this->assertEquals('developer', $team1Roles->first()->name);

        // 验证团队2的角色
        $team2Roles = $this->user->getRolesInTeam($this->team2->id);
        $this->assertEquals(1, $team2Roles->count());
        $this->assertEquals('tester', $team2Roles->first()->name);

        // 验证团队3的角色
        $team3Roles = $this->user->getRolesInTeam($this->team3->id);
        $this->assertEquals(1, $team3Roles->count());
        $this->assertEquals('admin', $team3Roles->first()->name);

        // 验证权限隔离
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'create_projects'));
        $this->assertFalse($this->user->hasPermissionInTeam($this->team1->id, 'manage_users'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team3->id, 'manage_users'));
    }

    /** @test */
    public function test_removeRoleSafely_method()
    {
        // 先分配多个角色
        $this->user->setCurrentTeamAsPermissionContext();
        $this->user->assignRoleSafely(['developer', 'tester']);

        $this->assertTrue($this->user->hasRole('developer'));
        $this->assertTrue($this->user->hasRole('tester'));

        // 移除一个角色
        $this->user->removeRoleSafely('developer');

        $this->assertFalse($this->user->hasRole('developer'));
        $this->assertTrue($this->user->hasRole('tester'));

        // 验证权限变化
        $this->assertFalse($this->user->hasPermissionToSafely('create_projects'));
        $this->assertTrue($this->user->hasPermissionToSafely('view_reports'));
    }

    /** @test */
    public function test_syncRolesSafely_method()
    {
        // 先分配一些角色
        $this->user->setCurrentTeamAsPermissionContext();
        $this->user->assignRoleSafely(['developer', 'tester']);

        $this->assertTrue($this->user->hasRole('developer'));
        $this->assertTrue($this->user->hasRole('tester'));

        // 同步角色（只保留admin）
        $this->user->syncRolesSafely(['admin']);

        $this->assertFalse($this->user->hasRole('developer'));
        $this->assertFalse($this->user->hasRole('tester'));
        $this->assertTrue($this->user->hasRole('admin'));

        // 验证权限变化
        $this->assertTrue($this->user->hasPermissionToSafely('manage_users'));
        $this->assertTrue($this->user->hasPermissionToSafely('system_admin'));

        // 测试清空所有角色
        $this->user->syncRolesSafely([]);
        $this->assertFalse($this->user->hasRole('admin'));
        $this->assertFalse($this->user->hasPermissionToSafely('manage_users'));
    }

    /** @test */
    public function test_setCurrentTeamAsPermissionContext_method()
    {
        // 在团队1分配角色
        $this->user->assignRoleInTeam($this->team1->id, 'developer');

        // 切换到团队2
        $this->user->current_team_id = $this->team2->id;
        $this->user->save();

        // 在团队2分配角色
        $this->user->assignRoleInTeam($this->team2->id, 'tester');

        // 设置当前团队为权限上下文
        $this->user->setCurrentTeamAsPermissionContext();

        // 验证权限上下文
        $currentContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
        $this->assertEquals($this->team2->id, $currentContext);

        // 验证权限检查使用正确的上下文
        $this->assertTrue($this->user->hasPermissionToSafely('view_reports'));
        $this->assertFalse($this->user->hasPermissionToSafely('create_projects'));
    }

    /** @test */
    public function test_withTeamContext_method()
    {
        // 在不同团队分配不同角色
        $this->user->assignRoleInTeam($this->team1->id, 'developer');
        $this->user->assignRoleInTeam($this->team2->id, 'admin');

        // 设置初始上下文为团队1
        $this->user->setCurrentTeamAsPermissionContext();

        // 在团队2上下文中执行操作
        $result = $this->user->withTeamContext($this->team2->id, function ($user) {
            return [
                'current_context' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
                'has_admin_permission' => $user->hasPermissionToSafely('manage_users'),
                'has_developer_permission' => $user->hasPermissionToSafely('create_projects'),
                'roles' => $user->roles->pluck('name')->toArray()
            ];
        });

        // 验证在团队2上下文中的结果
        $this->assertEquals($this->team2->id, $result['current_context']);
        $this->assertTrue($result['has_admin_permission']);
        $this->assertTrue($result['has_developer_permission']); // admin角色包含developer权限
        $this->assertContains('admin', $result['roles']);

        // 验证上下文已恢复
        $currentContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
        $this->assertEquals($this->team1->id, $currentContext);
    }

    /** @test */
    public function test_getRolesInTeam_method()
    {
        // 在不同团队分配不同角色
        $this->user->assignRoleInTeam($this->team1->id, ['developer', 'tester']);
        $this->user->assignRoleInTeam($this->team2->id, 'admin');

        // 获取团队1的角色
        $team1Roles = $this->user->getRolesInTeam($this->team1->id);
        $this->assertEquals(2, $team1Roles->count());

        $team1RoleNames = $team1Roles->pluck('name')->toArray();
        $this->assertContains('developer', $team1RoleNames);
        $this->assertContains('tester', $team1RoleNames);

        // 获取团队2的角色
        $team2Roles = $this->user->getRolesInTeam($this->team2->id);
        $this->assertEquals(1, $team2Roles->count());
        $this->assertEquals('admin', $team2Roles->first()->name);

        // 获取团队3的角色（应该为空）
        $team3Roles = $this->user->getRolesInTeam($this->team3->id);
        $this->assertEquals(0, $team3Roles->count());
    }

    /** @test */
    public function test_getAllRoles_method()
    {
        // 在不同团队分配角色
        $this->user->assignRoleInTeam($this->team1->id, 'developer');
        $this->user->assignRoleInTeam($this->team2->id, 'tester');
        $this->user->assignRoleInTeam($this->team3->id, 'admin');

        // 获取所有角色
        $allRoles = $this->user->getAllRoles();
        $this->assertEquals(3, $allRoles->count());

        // 验证角色名称
        $roleNames = $allRoles->pluck('name')->toArray();
        $this->assertContains('developer', $roleNames);
        $this->assertContains('tester', $roleNames);
        $this->assertContains('admin', $roleNames);

        // 验证团队信息
        foreach ($allRoles as $role) {
            $this->assertNotNull($role->pivot_team_id, "角色 {$role->name} 的 pivot_team_id 为空");
            $this->assertContains(
                $role->pivot_team_id,
                [$this->team1->id, $this->team2->id, $this->team3->id],
                "角色 {$role->name} 的团队ID不在期望范围内"
            );
        }
    }

    /** @test */
    public function test_complex_multi_team_scenario()
    {
        // 复杂的多团队场景测试

        // 1. 在团队1作为developer
        $this->user->assignRoleInTeam($this->team1->id, 'developer');

        // 2. 在团队2作为admin
        $this->user->assignRoleInTeam($this->team2->id, 'admin');

        // 3. 在团队3没有角色

        // 验证权限隔离
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'create_projects'));
        $this->assertFalse($this->user->hasPermissionInTeam($this->team1->id, 'manage_users'));

        $this->assertTrue($this->user->hasPermissionInTeam($this->team2->id, 'manage_users'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team2->id, 'create_projects'));

        $this->assertFalse($this->user->hasPermissionInTeam($this->team3->id, 'view_dashboard'));

        // 验证团队上下文切换
        $results = [];

        foreach ([$this->team1, $this->team2, $this->team3] as $team) {
            $results[$team->id] = $this->user->withTeamContext($team->id, function ($user) use ($team) {
                return [
                    'team_id' => $team->id,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'can_create' => $user->hasPermissionToSafely('create_projects'),
                    'can_manage' => $user->hasPermissionToSafely('manage_users'),
                ];
            });
        }

        // 验证结果
        $this->assertContains('developer', $results[$this->team1->id]['roles']);
        $this->assertTrue($results[$this->team1->id]['can_create']);
        $this->assertFalse($results[$this->team1->id]['can_manage']);

        $this->assertContains('admin', $results[$this->team2->id]['roles']);
        $this->assertTrue($results[$this->team2->id]['can_create']);
        $this->assertTrue($results[$this->team2->id]['can_manage']);

        $this->assertEmpty($results[$this->team3->id]['roles']);
        $this->assertFalse($results[$this->team3->id]['can_create']);
        $this->assertFalse($results[$this->team3->id]['can_manage']);
    }

    /** @test */
    public function test_performance_with_large_permission_set()
    {
        // 性能测试：大量权限检查
        $this->user->assignRoleInTeam($this->team1->id, 'admin');
        $this->user->setCurrentTeamAsPermissionContext();

        $permissions = [
            'view_dashboard',
            'create_projects',
            'edit_projects',
            'delete_projects',
            'manage_users',
            'view_reports',
            'export_data',
            'system_admin'
        ];

        $startTime = microtime(true);

        // 执行100次权限检查
        for ($i = 0; $i < 100; $i++) {
            foreach ($permissions as $permission) {
                $this->user->hasPermissionToSafely($permission);
            }
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // 性能断言：800次权限检查应该在1秒内完成
        $this->assertLessThan(1.0, $duration, "权限检查性能不达标，耗时: {$duration}秒");

        // 验证缓存工作正常
        $this->assertTrue($this->user->hasPermissionToSafely('manage_users'));
    }

    /** @test */
    public function test_error_handling_and_edge_cases()
    {
        // 测试错误处理和边界情况

        // 1. 检查不存在的权限
        $this->assertFalse($this->user->hasPermissionToSafely('nonexistent_permission'));
        $this->assertFalse($this->user->canSafely('another_nonexistent_permission'));

        // 2. 在没有团队上下文的情况下操作
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $this->user->current_team_id = null;
        $this->user->save();

        // 应该能够处理没有团队上下文的情况
        $this->assertFalse($this->user->hasPermissionToSafely('view_dashboard'));

        // 3. 测试空角色数组
        $this->user->current_team_id = $this->team1->id;
        $this->user->save();
        $this->user->setCurrentTeamAsPermissionContext();

        $this->user->syncRolesSafely([]);
        $this->assertEquals(0, $this->user->roles()->count());

        // 4. 测试不存在的团队ID
        $emptyRoles = $this->user->getRolesInTeam(99999);
        $this->assertEquals(0, $emptyRoles->count());
    }
}
