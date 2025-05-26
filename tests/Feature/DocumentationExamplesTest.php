<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * 文档示例测试类
 * 
 * 这个测试类验证权限与团队管理文档中提到的各种用法示例
 */
class DocumentationExamplesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $team1;
    protected Team $team2;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试用户
        $this->user = User::factory()->create([
            'name' => '文档示例测试用户',
            'email' => 'doc-test@example.com',
        ]);

        // 创建测试团队
        $this->team1 = Team::factory()->create(['id' => 100]);
        $this->team2 = Team::factory()->create(['id' => 200]);

        // 设置用户当前团队
        $this->user->current_team_id = $this->team1->id;
        $this->user->save();

        // 创建权限和角色
        $this->createPermissionsAndRoles();
    }

    protected function createPermissionsAndRoles()
    {
        // 为团队1创建权限和角色（使用doc_前缀避免与其他测试冲突）
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        $permissions1 = [
            'doc_view_orders_t1',
            'doc_edit_orders_t1',
            'doc_delete_orders_t1',
            'doc_view_products_t1',
            'doc_edit_products_t1',
            'doc_create_orders_t1'
        ];

        foreach ($permissions1 as $permissionName) {
            // 使用 firstOrCreate 避免重复创建
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ], [
                'team_id' => $this->team1->id
            ]);
        }

        // 使用 firstOrCreate 创建角色并分配权限
        $creatorRole = Role::firstOrCreate([
            'name' => 'doc_creator',
            'guard_name' => 'web'
        ], [
            'team_id' => $this->team1->id
        ]);
        if ($creatorRole->permissions->isEmpty()) {
            $creatorRole->givePermissionTo(['doc_view_orders_t1', 'doc_edit_orders_t1', 'doc_create_orders_t1']);
        }

        $editorRole = Role::firstOrCreate([
            'name' => 'doc_editor',
            'guard_name' => 'web'
        ], [
            'team_id' => $this->team1->id
        ]);
        if ($editorRole->permissions->isEmpty()) {
            $editorRole->givePermissionTo(['doc_view_orders_t1', 'doc_edit_orders_t1', 'doc_edit_products_t1']);
        }

        $viewerRole = Role::firstOrCreate([
            'name' => 'doc_viewer',
            'guard_name' => 'web'
        ], [
            'team_id' => $this->team1->id
        ]);
        if ($viewerRole->permissions->isEmpty()) {
            $viewerRole->givePermissionTo(['doc_view_orders_t1', 'doc_view_products_t1']);
        }

        // 为团队2创建权限和角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);

        $permissions2 = [
            'doc_view_orders_t2',
            'doc_edit_orders_t2',
            'doc_delete_orders_t2',
            'doc_view_products_t2',
            'doc_edit_products_t2',
            'doc_create_orders_t2'
        ];

        foreach ($permissions2 as $permissionName) {
            // 使用 firstOrCreate 避免重复创建
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ], [
                'team_id' => $this->team2->id
            ]);
        }

        if (!Role::where('name', 'doc_manager')->where('team_id', $this->team2->id)->exists()) {
            $managerRole = Role::create([
                'name' => 'doc_manager',
                'team_id' => $this->team2->id,
                'guard_name' => 'web'
            ]);
            $managerRole->givePermissionTo(['doc_view_orders_t2', 'doc_edit_orders_t2', 'doc_delete_orders_t2', 'doc_view_products_t2']);
        }
    }

    /** @test */
    public function test_basic_permission_check_examples_from_docs()
    {
        // 文档示例：基本权限检查
        $this->user->assignRoleSafely('doc_creator');

        // 检查单个权限
        $this->assertTrue($this->user->hasPermissionToSafely('doc_view_orders_t1'));
        $this->assertTrue($this->user->hasPermissionToSafely('doc_edit_orders_t1'));
        $this->assertFalse($this->user->hasPermissionToSafely('doc_delete_orders_t1'));

        // 检查权限对象
        $permission = Permission::where('name', 'doc_view_orders_t1')
            ->where('team_id', $this->team1->id)
            ->first();
        $this->assertTrue($this->user->hasPermissionToSafely($permission));

        // 使用 canSafely 方法
        $this->assertTrue($this->user->canSafely('doc_edit_orders_t1'));
        $this->assertFalse($this->user->canSafely('doc_delete_orders_t1'));
    }

    /** @test */
    public function test_batch_permission_check_examples_from_docs()
    {
        // 文档示例：批量权限检查
        $this->user->assignRoleSafely('doc_creator');

        $permissions = ['doc_view_orders_t1', 'doc_edit_orders_t1', 'doc_delete_orders_t1'];

        // 检查是否拥有任意一个权限
        $this->assertTrue($this->user->hasAnyPermissionSafely($permissions));

        // 检查是否拥有所有权限
        $this->assertFalse($this->user->hasAnyPermissionSafely($permissions, true));

        // 分配更多权限后再次检查
        $this->user->assignRoleSafely('doc_editor');
        $this->assertTrue($this->user->hasAnyPermissionSafely(['doc_view_orders_t1', 'doc_edit_orders_t1'], true));
    }

    /** @test */
    public function test_cross_team_permission_check_examples_from_docs()
    {
        // 文档示例：跨团队权限检查
        $this->user->assignRoleInTeam($this->team1->id, 'doc_creator');
        $this->user->assignRoleInTeam($this->team2->id, 'doc_manager');

        // 检查用户在团队1中的权限
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'doc_view_orders_t1'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'doc_edit_orders_t1'));
        $this->assertFalse($this->user->hasPermissionInTeam($this->team1->id, 'doc_delete_orders_t1'));

        // 检查用户在团队2中的权限
        $this->assertTrue($this->user->hasPermissionInTeam($this->team2->id, 'doc_view_orders_t2'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team2->id, 'doc_delete_orders_t2'));
    }

    /** @test */
    public function test_team_context_switching_examples_from_docs()
    {
        // 文档示例：团队上下文切换
        $this->user->assignRoleInTeam($this->team1->id, 'doc_creator');
        $this->user->assignRoleInTeam($this->team2->id, 'doc_manager');

        // 设置初始上下文
        $this->user->setCurrentTeamAsPermissionContext();
        $originalContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
        $this->assertEquals($this->team1->id, $originalContext);

        // 在团队2上下文中执行操作
        $result = $this->user->withTeamContext($this->team2->id, function ($user) {
            // 验证在正确的团队上下文中
            $currentContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
            $this->assertEquals($this->team2->id, $currentContext);

            // 检查权限
            $canView = $user->hasPermissionToSafely('doc_view_orders_t2');
            $canDelete = $user->hasPermissionToSafely('doc_delete_orders_t2');

            // 获取角色
            $roles = $user->roles->pluck('name')->toArray();

            return [
                'can_view' => $canView,
                'can_delete' => $canDelete,
                'roles' => $roles
            ];
        });

        // 验证操作结果
        $this->assertTrue($result['can_view']);
        $this->assertTrue($result['can_delete']);
        $this->assertContains('doc_manager', $result['roles']);

        // 验证上下文已恢复
        $restoredContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
        $this->assertEquals($originalContext, $restoredContext);
    }

    /** @test */
    public function test_role_assignment_examples_from_docs()
    {
        // 文档示例：角色分配

        // 分配单个角色到当前团队
        $this->user->assignRoleSafely('doc_creator');
        $this->assertTrue($this->user->hasRole('doc_creator'));

        // 分配多个角色
        $this->user->assignRoleSafely(['doc_editor', 'doc_viewer']);
        $this->assertTrue($this->user->hasRole('doc_editor'));
        $this->assertTrue($this->user->hasRole('doc_viewer'));

        // 跨团队分配角色
        $this->user->assignRoleInTeam($this->team2->id, 'doc_manager');
        $rolesInTeam2 = $this->user->getRolesInTeam($this->team2->id);
        $this->assertEquals(1, $rolesInTeam2->count());
        $this->assertEquals('doc_manager', $rolesInTeam2->first()->name);

        // 移除角色
        $this->user->removeRoleSafely('doc_creator');
        $this->assertFalse($this->user->hasRole('doc_creator'));

        // 同步角色（替换所有角色）
        $this->user->syncRolesSafely(['doc_editor']);
        $currentRoles = $this->user->roles->pluck('name')->toArray();
        $this->assertEquals(['doc_editor'], $currentRoles);
    }

    /** @test */
    public function test_role_and_permission_query_examples_from_docs()
    {
        // 文档示例：查看角色和权限
        $this->user->assignRoleSafely(['doc_creator', 'doc_editor']);
        $this->user->assignRoleInTeam($this->team2->id, 'doc_manager');

        // 查看当前团队的角色
        $currentRoles = $this->user->roles;
        $this->assertEquals(2, $currentRoles->count());

        // 查看指定团队的角色
        $team2Roles = $this->user->getRolesInTeam($this->team2->id);
        $this->assertEquals(1, $team2Roles->count());
        $this->assertEquals('doc_manager', $team2Roles->first()->name);

        // 查看所有团队的角色
        $allRoles = $this->user->getAllRoles();
        $this->assertEquals(3, $allRoles->count());

        // 查看角色名称
        $roleNames = $this->user->getRoleNames();
        $this->assertContains('doc_creator', $roleNames);
        $this->assertContains('doc_editor', $roleNames);
    }

    /** @test */
    public function test_permission_isolation_examples_from_docs()
    {
        // 文档示例：权限隔离验证

        // 在团队1分配角色
        $this->user->assignRoleInTeam($this->team1->id, 'doc_creator');

        // 验证用户在团队1有权限
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'doc_view_orders_t1'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'doc_edit_orders_t1'));

        // 验证用户在团队2没有权限（权限隔离）
        $this->assertFalse($this->user->hasPermissionInTeam($this->team2->id, 'doc_view_orders_t2'));
        $this->assertFalse($this->user->hasPermissionInTeam($this->team2->id, 'doc_edit_orders_t2'));

        // 在团队2分配角色后，验证权限隔离仍然有效
        $this->user->assignRoleInTeam($this->team2->id, 'doc_manager');

        // 团队1的权限不变
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'doc_view_orders_t1'));
        $this->assertFalse($this->user->hasPermissionInTeam($this->team1->id, 'doc_delete_orders_t1'));

        // 团队2有新的权限
        $this->assertTrue($this->user->hasPermissionInTeam($this->team2->id, 'doc_view_orders_t2'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team2->id, 'doc_delete_orders_t2'));
    }

    /** @test */
    public function test_complex_multi_team_scenario_from_docs()
    {
        // 文档示例：复杂的多团队场景

        // 创建第三个团队
        $team3 = Team::factory()->create(['id' => 300]);

        // 为团队3创建权限和角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($team3->id);

        Permission::create(['name' => 'doc_approve_orders', 'team_id' => $team3->id, 'guard_name' => 'web']);
        $approverRole = Role::create(['name' => 'doc_approver', 'team_id' => $team3->id, 'guard_name' => 'web']);
        $approverRole->givePermissionTo('doc_approve_orders');

        // 在多个团队分配不同角色
        $this->user->assignRoleInTeam($this->team1->id, 'doc_creator');
        $this->user->assignRoleInTeam($this->team2->id, 'doc_manager');
        $this->user->assignRoleInTeam($team3->id, 'doc_approver');

        // 验证团队1权限（doc_creator角色）
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'doc_view_orders_t1'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team1->id, 'doc_edit_orders_t1'));
        $this->assertFalse($this->user->hasPermissionInTeam($this->team1->id, 'doc_delete_orders_t1'));
        $this->assertFalse($this->user->hasPermissionInTeam($this->team1->id, 'doc_approve_orders'));

        // 验证团队2权限（doc_manager角色）
        $this->assertTrue($this->user->hasPermissionInTeam($this->team2->id, 'doc_view_orders_t2'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team2->id, 'doc_edit_orders_t2'));
        $this->assertTrue($this->user->hasPermissionInTeam($this->team2->id, 'doc_delete_orders_t2'));
        $this->assertFalse($this->user->hasPermissionInTeam($this->team2->id, 'doc_approve_orders'));

        // 验证团队3权限（doc_approver角色）
        $this->assertFalse($this->user->hasPermissionInTeam($team3->id, 'doc_view_orders_t1'));
        $this->assertFalse($this->user->hasPermissionInTeam($team3->id, 'doc_edit_orders_t1'));
        $this->assertFalse($this->user->hasPermissionInTeam($team3->id, 'doc_delete_orders_t1'));
        $this->assertTrue($this->user->hasPermissionInTeam($team3->id, 'doc_approve_orders'));
    }

    /** @test */
    public function test_automatic_team_context_examples_from_docs()
    {
        // 文档示例：自动团队上下文

        // 验证用户当前团队
        $this->assertEquals($this->team1->id, $this->user->current_team_id);

        // 设置当前团队为权限上下文
        $this->user->setCurrentTeamAsPermissionContext();

        $registrar = app(PermissionRegistrar::class);
        $contextTeamId = $registrar->getPermissionsTeamId();
        $this->assertEquals($this->team1->id, $contextTeamId);

        // 权限操作自动使用当前团队上下文
        $this->user->assignRoleSafely('doc_creator');

        // 验证角色分配到正确的团队
        $rolesInTeam1 = $this->user->getRolesInTeam($this->team1->id);
        $this->assertEquals(1, $rolesInTeam1->count());
        $this->assertEquals('doc_creator', $rolesInTeam1->first()->name);

        // 权限检查自动使用当前团队上下文
        $this->assertTrue($this->user->hasPermissionToSafely('doc_view_orders_t1'));
        $this->assertTrue($this->user->hasPermissionToSafely('doc_edit_orders_t1'));
    }

    /** @test */
    public function test_performance_considerations_from_docs()
    {
        // 文档示例：性能考虑

        // 分配角色
        $this->user->assignRoleSafely('doc_creator');

        // 第一次权限检查（会缓存结果）
        $startTime = microtime(true);
        $hasPermission1 = $this->user->hasPermissionToSafely('doc_view_orders_t1');
        $firstCheckTime = microtime(true) - $startTime;

        // 第二次权限检查（使用缓存）
        $startTime = microtime(true);
        $hasPermission2 = $this->user->hasPermissionToSafely('doc_view_orders_t1');
        $secondCheckTime = microtime(true) - $startTime;

        // 验证结果一致
        $this->assertTrue($hasPermission1);
        $this->assertTrue($hasPermission2);

        // 第二次检查应该更快（使用缓存）
        $this->assertLessThanOrEqual($firstCheckTime, $secondCheckTime);

        echo "\n第一次权限检查时间: " . round($firstCheckTime * 1000, 2) . "ms";
        echo "\n第二次权限检查时间: " . round($secondCheckTime * 1000, 2) . "ms\n";
    }
}
