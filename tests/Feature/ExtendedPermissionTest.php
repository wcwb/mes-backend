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
use Illuminate\Support\Facades\Cache;

/**
 * 扩展权限测试类
 * 
 * 测试更多复杂场景和边界情况，包括：
 * - 性能优化测试
 * - 缓存机制测试
 * - 错误处理测试
 * - 边界条件测试
 * - 安全性测试
 */
class ExtendedPermissionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $team1;
    protected $team2;
    protected $team3;
    protected $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建多个测试团队
        $this->team1 = Team::factory()->create(['id' => 20, 'name' => '开发团队']);
        $this->team2 = Team::factory()->create(['id' => 21, 'name' => '测试团队']);
        $this->team3 = Team::factory()->create(['id' => 22, 'name' => '运营团队']);

        // 创建测试用户
        $this->user = User::factory()->create(['current_team_id' => $this->team1->id]);

        // 设置用户在多个团队中的成员关系
        $this->team1->users()->attach($this->user->id, ['role' => 'developer']);
        $this->team2->users()->attach($this->user->id, ['role' => 'tester']);
        $this->team3->users()->attach($this->user->id, ['role' => 'operator']);

        // 创建测试权限
        $this->permissions = [
            'extended_view_code',
            'extended_edit_code',
            'extended_deploy_code',
            'extended_view_tests',
            'extended_run_tests',
            'extended_view_reports',
            'extended_edit_reports',
            'extended_manage_users',
            'extended_system_admin'
        ];

        $this->createPermissionsAndRoles();
    }

    protected function createPermissionsAndRoles()
    {
        // 为团队1创建开发相关权限和角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        foreach (['extended_view_code', 'extended_edit_code', 'extended_deploy_code'] as $permissionName) {
            $uniqueName = $permissionName . '_team_' . $this->team1->id;
            Permission::create([
                'name' => $uniqueName,
                'team_id' => $this->team1->id,
                'guard_name' => 'web'
            ]);
        }

        $developerRole = Role::create([
            'name' => 'developer_team_' . $this->team1->id,
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $developerRole->givePermissionTo([
            'extended_view_code_team_' . $this->team1->id,
            'extended_edit_code_team_' . $this->team1->id
        ]);

        // 为团队2创建测试相关权限和角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);

        foreach (['extended_view_tests', 'extended_run_tests'] as $permissionName) {
            $uniqueName = $permissionName . '_team_' . $this->team2->id;
            Permission::create([
                'name' => $uniqueName,
                'team_id' => $this->team2->id,
                'guard_name' => 'web'
            ]);
        }

        $testerRole = Role::create([
            'name' => 'tester_team_' . $this->team2->id,
            'team_id' => $this->team2->id,
            'guard_name' => 'web'
        ]);

        $testerRole->givePermissionTo([
            'extended_view_tests_team_' . $this->team2->id,
            'extended_run_tests_team_' . $this->team2->id
        ]);

        // 为团队3创建运营相关权限和角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team3->id);

        foreach (['extended_view_reports', 'extended_edit_reports', 'extended_manage_users'] as $permissionName) {
            $uniqueName = $permissionName . '_team_' . $this->team3->id;
            Permission::create([
                'name' => $uniqueName,
                'team_id' => $this->team3->id,
                'guard_name' => 'web'
            ]);
        }

        $operatorRole = Role::create([
            'name' => 'operator_team_' . $this->team3->id,
            'team_id' => $this->team3->id,
            'guard_name' => 'web'
        ]);

        $operatorRole->givePermissionTo([
            'extended_view_reports_team_' . $this->team3->id,
            'extended_edit_reports_team_' . $this->team3->id
        ]);
    }

    /** @test */
    public function user_can_handle_multiple_team_contexts_efficiently()
    {
        // 在所有团队分配角色
        $this->user->assignRoleInTeam($this->team1->id, 'developer_team_' . $this->team1->id);
        $this->user->assignRoleInTeam($this->team2->id, 'tester_team_' . $this->team2->id);
        $this->user->assignRoleInTeam($this->team3->id, 'operator_team_' . $this->team3->id);

        // 测试批量权限检查的性能
        $startTime = microtime(true);

        $results = [];
        foreach ([$this->team1->id, $this->team2->id, $this->team3->id] as $teamId) {
            $results[$teamId] = $this->user->withTeamContext($teamId, function ($user) use ($teamId) {
                return [
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'permissions_count' => $user->getAllPermissions()->count(),
                    'team_id' => $teamId
                ];
            });
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // 验证结果
        $this->assertCount(3, $results);
        $this->assertContains('developer_team_' . $this->team1->id, $results[$this->team1->id]['roles']);
        $this->assertContains('tester_team_' . $this->team2->id, $results[$this->team2->id]['roles']);
        $this->assertContains('operator_team_' . $this->team3->id, $results[$this->team3->id]['roles']);

        // 性能断言（应该在合理时间内完成）
        $this->assertLessThan(1.0, $executionTime, '多团队上下文切换应该在1秒内完成');

        echo "\n多团队上下文切换执行时间: " . round($executionTime * 1000, 2) . "ms\n";
    }

    /** @test */
    public function user_can_handle_nonexistent_permissions_gracefully()
    {
        // 分配角色并设置团队上下文
        $this->user->assignRoleInTeam($this->team1->id, 'developer_team_' . $this->team1->id);
        $this->user->setCurrentTeamAsPermissionContext();

        // 测试不存在的权限
        $this->assertFalse($this->user->hasPermissionToSafely('nonexistent_permission'));
        $this->assertFalse($this->user->canSafely('another_nonexistent_permission'));

        // 测试跨团队检查不存在的权限
        $this->assertFalse($this->user->hasPermissionInTeam($this->team1->id, 'nonexistent_permission'));

        // 测试批量权限检查包含不存在的权限
        $mixedPermissions = [
            'extended_view_code_team_' . $this->team1->id, // 存在
            'nonexistent_permission_1', // 不存在
            'extended_edit_code_team_' . $this->team1->id, // 存在
            'nonexistent_permission_2'  // 不存在
        ];

        $hasAny = $this->user->hasAnyPermissionSafely($mixedPermissions);
        $this->assertTrue($hasAny, '应该检测到存在的权限');

        $hasAll = $this->user->hasAnyPermissionSafely($mixedPermissions, true);
        $this->assertFalse($hasAll, '不应该拥有所有权限（包含不存在的权限）');
    }

    /** @test */
    public function user_can_handle_empty_current_team_gracefully()
    {
        // 创建没有当前团队的用户
        $userWithoutTeam = User::factory()->create(['current_team_id' => null]);

        // 测试权限检查应该返回false而不是抛出异常
        $this->assertFalse($userWithoutTeam->hasPermissionToSafely('any_permission'));
        $this->assertFalse($userWithoutTeam->canSafely('any_permission'));

        // 测试角色分配应该失败但不抛出异常
        try {
            $userWithoutTeam->assignRoleSafely('any_role');
            $this->fail('应该抛出异常，因为没有当前团队或角色不存在');
        } catch (\Exception $e) {
            // 可能是因为没有当前团队或角色不存在
            $this->assertTrue(
                str_contains(strtolower($e->getMessage()), 'current_team_id') ||
                    str_contains(strtolower($e->getMessage()), 'role'),
                '异常消息应该包含 current_team_id 或 role 相关信息'
            );
        }

        // 测试设置当前团队后应该正常工作
        $userWithoutTeam->current_team_id = $this->team1->id;
        $userWithoutTeam->save();

        $userWithoutTeam->assignRoleInTeam($this->team1->id, 'developer_team_' . $this->team1->id);
        $this->assertTrue($userWithoutTeam->hasPermissionInTeam($this->team1->id, 'extended_view_code_team_' . $this->team1->id));
    }

    /** @test */
    public function user_can_handle_role_assignment_edge_cases()
    {
        // 设置团队上下文
        $this->user->setCurrentTeamAsPermissionContext();

        // 测试分配不存在的角色
        try {
            $this->user->assignRoleSafely('nonexistent_role');
            $this->fail('应该抛出异常，因为角色不存在');
        } catch (\Exception $e) {
            $this->assertStringContainsString('role', strtolower($e->getMessage()));
        }

        // 测试重复分配相同角色
        $this->user->assignRoleInTeam($this->team1->id, 'developer_team_' . $this->team1->id);
        $this->user->assignRoleInTeam($this->team1->id, 'developer_team_' . $this->team1->id); // 重复分配

        $roles = $this->user->getRolesInTeam($this->team1->id);
        $this->assertEquals(1, $roles->count(), '重复分配角色不应该创建重复记录');

        // 测试移除不存在的角色 - 使用try-catch来处理可能的异常
        try {
            $this->user->removeRoleSafely('nonexistent_role'); // 可能抛出异常
        } catch (\Exception $e) {
            // 静默处理异常，这是预期的行为
        }

        // 验证现有角色未受影响
        $rolesAfterRemoval = $this->user->getRolesInTeam($this->team1->id);
        $this->assertEquals(1, $rolesAfterRemoval->count());
    }

    /** @test */
    public function user_can_handle_complex_permission_inheritance()
    {
        // 设置团队上下文
        $this->user->setCurrentTeamAsPermissionContext();

        // 创建具有继承关系的角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        // 创建高级开发者角色，继承开发者权限并添加部署权限
        $seniorDeveloperRole = Role::create([
            'name' => 'senior_developer_team_' . $this->team1->id,
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $seniorDeveloperRole->givePermissionTo([
            'extended_view_code_team_' . $this->team1->id,
            'extended_edit_code_team_' . $this->team1->id,
            'extended_deploy_code_team_' . $this->team1->id
        ]);

        // 分配高级开发者角色
        $this->user->assignRoleInTeam($this->team1->id, 'senior_developer_team_' . $this->team1->id);

        // 验证权限继承 - 使用团队上下文进行检查
        $this->assertTrue($this->user->withTeamContext($this->team1->id, function ($user) {
            return $user->hasPermissionTo('extended_view_code_team_' . $this->team1->id);
        }));
        $this->assertTrue($this->user->withTeamContext($this->team1->id, function ($user) {
            return $user->hasPermissionTo('extended_edit_code_team_' . $this->team1->id);
        }));
        $this->assertTrue($this->user->withTeamContext($this->team1->id, function ($user) {
            return $user->hasPermissionTo('extended_deploy_code_team_' . $this->team1->id);
        }));

        // 测试多角色权限合并
        $this->user->assignRoleInTeam($this->team1->id, 'developer_team_' . $this->team1->id);

        $allPermissions = $this->user->withTeamContext($this->team1->id, function ($user) {
            return $user->getAllPermissions()->pluck('name')->toArray();
        });

        // 验证权限不重复
        $uniquePermissions = array_unique($allPermissions);
        $this->assertEquals(count($allPermissions), count($uniquePermissions), '权限列表不应该有重复');

        // 验证至少有基本权限
        $this->assertGreaterThan(0, count($allPermissions), '应该至少有一些权限');
    }

    /** @test */
    public function user_can_handle_team_context_restoration_after_exceptions()
    {
        // 设置初始团队上下文
        $this->user->setCurrentTeamAsPermissionContext();
        $originalContext = app(PermissionRegistrar::class)->getPermissionsTeamId();

        // 在团队上下文中执行可能抛出异常的操作
        try {
            $this->user->withTeamContext($this->team2->id, function ($user) {
                // 验证上下文已切换
                $currentContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
                $this->assertEquals($this->team2->id, $currentContext);

                // 抛出异常
                throw new \Exception('测试异常');
            });
        } catch (\Exception $e) {
            // 验证异常被正确抛出
            $this->assertEquals('测试异常', $e->getMessage());
        }

        // 验证团队上下文已恢复
        $restoredContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
        $this->assertEquals($originalContext, $restoredContext, '团队上下文应该在异常后恢复');
    }

    /** @test */
    public function user_can_handle_concurrent_team_operations()
    {
        // 设置初始团队上下文
        $this->user->setCurrentTeamAsPermissionContext();
        $initialContext = app(PermissionRegistrar::class)->getPermissionsTeamId();

        // 模拟并发操作场景
        $this->user->assignRoleInTeam($this->team1->id, 'developer_team_' . $this->team1->id);
        $this->user->assignRoleInTeam($this->team2->id, 'tester_team_' . $this->team2->id);

        // 快速连续的团队上下文切换
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $teamId = ($i % 2 === 0) ? $this->team1->id : $this->team2->id;
            $iteration = $i;

            $results[] = $this->user->withTeamContext($teamId, function ($user) use ($teamId, $iteration) {
                // 获取当前团队的角色数量
                $rolesInCurrentTeam = $user->getRolesInTeam($teamId);

                return [
                    'iteration' => $iteration,
                    'team_id' => $teamId,
                    'context_id' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
                    'roles_count' => $rolesInCurrentTeam->count()
                ];
            });
        }

        // 验证每次操作都在正确的团队上下文中
        foreach ($results as $result) {
            $this->assertEquals(
                $result['team_id'],
                $result['context_id'],
                "第{$result['iteration']}次操作的团队上下文不正确"
            );
            $this->assertEquals(
                1,
                $result['roles_count'],
                "第{$result['iteration']}次操作的角色数量不正确"
            );
        }

        // 验证最终上下文恢复正确
        $finalContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
        // withTeamContext 应该恢复到初始上下文
        $this->assertEquals(
            $initialContext,
            $finalContext,
            "最终上下文应该恢复到初始上下文({$initialContext})，实际是: {$finalContext}"
        );
    }

    /** @test */
    public function user_can_validate_permission_system_integrity()
    {
        // 分配角色到所有团队
        $this->user->assignRoleInTeam($this->team1->id, 'developer_team_' . $this->team1->id);
        $this->user->assignRoleInTeam($this->team2->id, 'tester_team_' . $this->team2->id);
        $this->user->assignRoleInTeam($this->team3->id, 'operator_team_' . $this->team3->id);

        // 验证权限系统完整性
        $integrityReport = [
            'total_teams' => 3,
            'total_roles' => 0,
            'total_permissions' => 0,
            'team_isolation' => true,
            'permission_consistency' => true
        ];

        // 检查每个团队的权限隔离
        foreach ([$this->team1->id, $this->team2->id, $this->team3->id] as $teamId) {
            $teamRoles = $this->user->getRolesInTeam($teamId);
            $integrityReport['total_roles'] += $teamRoles->count();

            $teamPermissions = $this->user->withTeamContext($teamId, function ($user) {
                return $user->getAllPermissions();
            });
            $integrityReport['total_permissions'] += $teamPermissions->count();

            // 验证权限只属于当前团队
            foreach ($teamPermissions as $permission) {
                if ($permission->team_id !== $teamId) {
                    $integrityReport['team_isolation'] = false;
                    break;
                }
            }
        }

        // 验证跨团队权限不会泄露
        $team1Permissions = $this->user->withTeamContext($this->team1->id, function ($user) {
            return $user->getAllPermissions()->pluck('name')->toArray();
        });

        $team2Permissions = $this->user->withTeamContext($this->team2->id, function ($user) {
            return $user->getAllPermissions()->pluck('name')->toArray();
        });

        $permissionOverlap = array_intersect($team1Permissions, $team2Permissions);
        if (!empty($permissionOverlap)) {
            $integrityReport['permission_consistency'] = false;
        }

        // 断言系统完整性
        $this->assertEquals(3, $integrityReport['total_roles'], '应该有3个角色（每个团队一个）');
        $this->assertGreaterThan(0, $integrityReport['total_permissions'], '应该有权限存在');
        $this->assertTrue($integrityReport['team_isolation'], '团队权限应该完全隔离');
        $this->assertTrue($integrityReport['permission_consistency'], '不同团队的权限不应该重叠');

        echo "\n权限系统完整性报告:\n";
        echo "- 总团队数: {$integrityReport['total_teams']}\n";
        echo "- 总角色数: {$integrityReport['total_roles']}\n";
        echo "- 总权限数: {$integrityReport['total_permissions']}\n";
        echo "- 团队隔离: " . ($integrityReport['team_isolation'] ? '✓' : '✗') . "\n";
        echo "- 权限一致性: " . ($integrityReport['permission_consistency'] ? '✓' : '✗') . "\n";
    }
}
