<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

/**
 * 故障排除测试类
 * 
 * 基于权限与团队管理文档中的故障排除指南，测试常见问题和边界情况
 */
class TroubleshootingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $team1;
    protected Team $team2;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试用户和团队
        $this->team1 = Team::factory()->create(['id' => 10]);
        $this->team2 = Team::factory()->create(['id' => 11]);

        $this->user = User::factory()->create([
            'current_team_id' => $this->team1->id
        ]);

        // 创建权限和角色
        $this->createPermissionsAndRoles();
    }

    protected function createPermissionsAndRoles()
    {
        // 为团队1创建权限和角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        $viewOrdersPermission = Permission::create([
            'name' => 'view_orders_team_' . $this->team1->id,
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $editOrdersPermission = Permission::create([
            'name' => 'edit_orders_team_' . $this->team1->id,
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $creatorRole = Role::create([
            'name' => 'creator_team_' . $this->team1->id,
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $creatorRole->givePermissionTo([$viewOrdersPermission, $editOrdersPermission]);

        // 为团队2创建权限和角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);

        $viewOrdersPermission2 = Permission::create([
            'name' => 'view_orders_team_' . $this->team2->id,
            'team_id' => $this->team2->id,
            'guard_name' => 'web'
        ]);

        $editOrdersPermission2 = Permission::create([
            'name' => 'edit_orders_team_' . $this->team2->id,
            'team_id' => $this->team2->id,
            'guard_name' => 'web'
        ]);

        $creatorRole2 = Role::create([
            'name' => 'creator_team_' . $this->team2->id,
            'team_id' => $this->team2->id,
            'guard_name' => 'web'
        ]);

        // 给团队2的角色分配权限
        $creatorRole2->givePermissionTo([$viewOrdersPermission2, $editOrdersPermission2]);
    }

    /** @test */
    public function can_debug_permission_check_failures()
    {
        // 分配角色到用户
        $this->user->assignRoleInTeam($this->team1->id, 'creator_team_' . $this->team1->id);

        // 测试调试步骤1：检查用户当前团队
        $this->assertEquals($this->team1->id, $this->user->current_team_id);

        // 测试调试步骤2：检查权限注册器的团队上下文
        $this->user->setCurrentTeamAsPermissionContext();
        $registrar = app(PermissionRegistrar::class);
        $this->assertEquals($this->team1->id, $registrar->getPermissionsTeamId());

        // 测试调试步骤3：检查用户在当前团队的角色
        $roles = $this->user->roles;
        $this->assertEquals(1, $roles->count());
        $this->assertEquals('creator_team_' . $this->team1->id, $roles->first()->name);

        // 测试调试步骤4：检查角色的权限
        $role = $roles->first();
        $permissions = $role->permissions;
        $this->assertEquals(2, $permissions->count());
        $this->assertTrue($permissions->contains('name', 'view_orders_team_' . $this->team1->id));
        $this->assertTrue($permissions->contains('name', 'edit_orders_team_' . $this->team1->id));

        // 测试调试步骤5：使用权限对象进行检查
        $permission = Permission::where('name', 'view_orders_team_' . $this->team1->id)
            ->where('team_id', $this->team1->id)
            ->first();

        $this->assertNotNull($permission);
        $this->assertTrue($this->user->hasPermissionTo($permission));
    }

    /** @test */
    public function can_handle_role_assignment_failures()
    {
        // 测试角色不存在的情况
        try {
            $this->user->assignRoleSafely('nonexistent_role');
            $this->fail('应该抛出异常');
        } catch (\Exception $e) {
            $this->assertStringContainsString('role', strtolower($e->getMessage()));
        }

        // 测试验证角色存在的解决方案
        $role = Role::where('name', 'creator_team_' . $this->team1->id)
            ->where('team_id', $this->team1->id)
            ->first();

        $this->assertNotNull($role, '角色应该存在');

        // 成功分配存在的角色
        $this->user->assignRoleSafely('creator_team_' . $this->team1->id);
        $this->assertTrue($this->user->hasRole('creator_team_' . $this->team1->id));
    }

    /** @test */
    public function can_debug_team_context_confusion()
    {
        // 创建调试函数的测试版本
        $debugLog = [];
        $debugTeamContext = function ($label) use (&$debugLog) {
            $registrar = app(PermissionRegistrar::class);
            $currentTeamId = $registrar->getPermissionsTeamId();
            $debugLog[] = "[{$label}] 当前团队上下文: " . ($currentTeamId ?? 'null');
            return $currentTeamId;
        };

        // 先清除任何现有的团队上下文
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        // 测试上下文变化
        $context1 = $debugTeamContext('开始');
        $this->assertNull($context1);

        $this->user->setCurrentTeamAsPermissionContext();
        $context2 = $debugTeamContext('设置用户团队后');
        $this->assertEquals($this->team1->id, $context2);

        $this->user->assignRoleSafely('creator_team_' . $this->team1->id);
        $context3 = $debugTeamContext('分配角色后');
        $this->assertEquals($this->team1->id, $context3);

        // 验证调试日志
        $this->assertCount(3, $debugLog);
        $this->assertStringContainsString('开始', $debugLog[0]);
        $this->assertStringContainsString('设置用户团队后', $debugLog[1]);
        $this->assertStringContainsString('分配角色后', $debugLog[2]);
    }

    /** @test */
    public function can_handle_context_isolation_with_withTeamContext()
    {
        // 分配角色到两个团队
        $this->user->assignRoleInTeam($this->team1->id, 'creator_team_' . $this->team1->id);
        $this->user->assignRoleInTeam($this->team2->id, 'creator_team_' . $this->team2->id);

        // 设置初始上下文
        $this->user->setCurrentTeamAsPermissionContext();
        $originalContext = app(PermissionRegistrar::class)->getPermissionsTeamId();

        // 使用 withTeamContext 确保上下文隔离
        $result = $this->user->withTeamContext($this->team2->id, function ($user) {
            // 验证在正确的团队上下文中
            $currentContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
            $this->assertEquals($this->team2->id, $currentContext);

            // 检查权限 - 使用用户在团队2的角色对应的权限
            // creator角色应该有view_orders权限
            return $user->hasPermissionToSafely('view_orders_team_' . $this->team2->id);
        });

        // 验证操作完成后上下文恢复
        $restoredContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
        $this->assertEquals($originalContext, $restoredContext);

        // 验证操作结果 - 用户应该有权限，因为已经分配了对应的角色
        // 先验证用户确实在团队2有角色
        $rolesInTeam2 = $this->user->getRolesInTeam($this->team2->id);
        $this->assertGreaterThan(0, $rolesInTeam2->count(), '用户在团队2应该有角色');

        $this->assertTrue($result, '用户在团队2应该有view_orders权限');
    }

    /** @test */
    public function can_handle_performance_optimization_scenarios()
    {
        // 创建多个用户和角色用于性能测试
        $users = User::factory()->count(5)->create(['current_team_id' => $this->team1->id]);

        // 设置正确的团队上下文并分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        foreach ($users as $user) {
            $user->assignRoleInTeam($this->team1->id, 'creator_team_' . $this->team1->id);
        }

        // 测试预加载关系避免 N+1 查询
        $startTime = microtime(true);

        // 在正确的团队上下文中查询用户和角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $usersWithRoles = User::with(['roles.permissions'])
            ->whereIn('id', $users->pluck('id'))
            ->get();

        $endTime = microtime(true);
        $queryTime = ($endTime - $startTime) * 1000; // 转换为毫秒

        // 验证预加载有效
        foreach ($usersWithRoles as $user) {
            // 检查用户在团队1的角色
            $rolesInTeam1 = $user->getRolesInTeam($this->team1->id);
            $this->assertGreaterThan(0, $rolesInTeam1->count(), '用户应该在团队1有角色');

            // 检查角色的权限
            $role = $rolesInTeam1->first();
            $permissions = $role->permissions;
            $this->assertGreaterThan(0, $permissions->count(), '角色应该有权限');
        }

        // 验证查询时间被记录（不强制要求特定性能）
        $this->assertGreaterThan(0, $queryTime, "查询时间应该大于0ms");

        echo "\n预加载查询执行时间: " . round($queryTime, 2) . "ms\n";
    }

    /** @test */
    public function can_handle_batch_permission_checks()
    {
        // 分配角色
        $this->user->assignRoleInTeam($this->team1->id, 'creator_team_' . $this->team1->id);
        $this->user->setCurrentTeamAsPermissionContext();

        // 测试批量权限检查
        $permissions = [
            'view_orders_team_' . $this->team1->id,
            'edit_orders_team_' . $this->team1->id,
            'nonexistent_permission'
        ];

        // 使用 hasAnyPermission 进行批量检查
        $hasAnyPermission = $this->user->hasAnyPermission($permissions);
        $this->assertTrue($hasAnyPermission, '用户应该至少有一个权限');

        // 检查具体权限
        $this->assertTrue($this->user->hasPermissionToSafely('view_orders_team_' . $this->team1->id));
        $this->assertTrue($this->user->hasPermissionToSafely('edit_orders_team_' . $this->team1->id));
        $this->assertFalse($this->user->hasPermissionToSafely('nonexistent_permission'));
    }

    /** @test */
    public function can_handle_cache_related_issues()
    {
        // 分配角色
        $this->user->assignRoleInTeam($this->team1->id, 'creator_team_' . $this->team1->id);
        $this->user->setCurrentTeamAsPermissionContext();

        // 第一次权限检查（会缓存结果）
        $hasPermission1 = $this->user->hasPermissionToSafely('view_orders_team_' . $this->team1->id);
        $this->assertTrue($hasPermission1);

        // 移除角色
        $this->user->removeRole('creator_team_' . $this->team1->id);

        // 清除权限缓存
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 再次检查权限（应该反映最新状态）
        $hasPermission2 = $this->user->hasPermissionToSafely('view_orders_team_' . $this->team1->id);
        $this->assertFalse($hasPermission2, '移除角色后权限检查应该返回false');
    }

    /** @test */
    public function can_validate_cross_team_permission_isolation()
    {
        // 在团队1分配角色
        $this->user->assignRoleInTeam($this->team1->id, 'creator_team_' . $this->team1->id);

        // 验证用户在团队1有权限
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'view_orders_team_' . $this->team1->id));

        // 验证用户在团队2没有权限（权限隔离）
        $this->assertFalse($this->user->hasPermissionInTeam($this->team2->id, 'view_orders_team_' . $this->team2->id));

        // 验证不能访问其他团队的同名权限
        $this->assertFalse($this->user->hasPermissionInTeam($this->team1->id, 'view_orders_team_' . $this->team2->id));
    }

    /** @test */
    public function can_handle_manual_context_management_safely()
    {
        $registrar = app(PermissionRegistrar::class);

        // 记录原始上下文
        $originalTeamId = $registrar->getPermissionsTeamId();

        try {
            // 手动设置团队上下文
            $registrar->setPermissionsTeamId($this->team1->id);
            $registrar->forgetCachedPermissions();

            // 分配角色
            $this->user->assignRole('creator_team_' . $this->team1->id);

            // 验证角色分配成功
            $this->assertTrue($this->user->hasRole('creator_team_' . $this->team1->id));
        } finally {
            // 确保恢复原始上下文
            $registrar->setPermissionsTeamId($originalTeamId);
            $registrar->forgetCachedPermissions();
        }

        // 验证上下文已恢复
        $finalContext = $registrar->getPermissionsTeamId();
        $this->assertEquals($originalTeamId, $finalContext);
    }
}
