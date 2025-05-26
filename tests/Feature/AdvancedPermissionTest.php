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

class AdvancedPermissionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $team1;
    protected $team2;
    protected $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试团队
        $this->team1 = Team::factory()->create(['id' => 10, 'name' => '团队1']);
        $this->team2 = Team::factory()->create(['id' => 11, 'name' => '团队2']);

        // 创建测试用户
        $this->user = User::factory()->create(['current_team_id' => $this->team1->id]);

        // 设置用户在两个团队中的成员关系
        $this->team1->users()->attach($this->user->id, ['role' => 'member']);
        $this->team2->users()->attach($this->user->id, ['role' => 'admin']);

        // 创建测试权限（使用唯一的权限名称）
        $this->permissions = [
            'advanced_view_orders',
            'advanced_create_orders',
            'advanced_edit_orders',
            'advanced_delete_orders',
            'advanced_approve_orders',
            'advanced_view_products',
            'advanced_edit_products',
            'advanced_manage_team',
            'advanced_invite_users'
        ];

        $this->createPermissionsAndRoles();
    }

    protected function createPermissionsAndRoles()
    {
        // 为团队1创建权限和角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        foreach ($this->permissions as $permissionName) {
            $uniqueName = $permissionName . '_team_' . $this->team1->id;
            Permission::create([
                'name' => $uniqueName,
                'team_id' => $this->team1->id,
                'guard_name' => 'web'
            ]);
        }

        $editorRole = Role::create([
            'name' => 'editor_team_' . $this->team1->id,
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $editorRole->givePermissionTo([
            'advanced_view_orders_team_' . $this->team1->id,
            'advanced_create_orders_team_' . $this->team1->id,
            'advanced_edit_orders_team_' . $this->team1->id,
            'advanced_view_products_team_' . $this->team1->id,
            'advanced_edit_products_team_' . $this->team1->id
        ]);

        // 为团队2创建权限和角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);

        foreach ($this->permissions as $permissionName) {
            $uniqueName = $permissionName . '_team_' . $this->team2->id;
            Permission::create([
                'name' => $uniqueName,
                'team_id' => $this->team2->id,
                'guard_name' => 'web'
            ]);
        }

        $managerRole = Role::create([
            'name' => 'manager_team_' . $this->team2->id,
            'team_id' => $this->team2->id,
            'guard_name' => 'web'
        ]);

        $managerRole->givePermissionTo([
            'advanced_view_orders_team_' . $this->team2->id,
            'advanced_create_orders_team_' . $this->team2->id,
            'advanced_edit_orders_team_' . $this->team2->id,
            'advanced_delete_orders_team_' . $this->team2->id,
            'advanced_approve_orders_team_' . $this->team2->id,
            'advanced_manage_team_team_' . $this->team2->id,
            'advanced_invite_users_team_' . $this->team2->id
        ]);
    }

    /** @test */
    public function user_can_check_batch_permissions_safely()
    {
        // 设置团队1上下文并分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $this->user->assignRole('editor_team_' . $this->team1->id);

        $orderPermissions = [
            'advanced_view_orders_team_' . $this->team1->id,
            'advanced_create_orders_team_' . $this->team1->id,
            'advanced_edit_orders_team_' . $this->team1->id,
            'advanced_delete_orders_team_' . $this->team1->id
        ];

        // 检查是否拥有任意一个权限
        $hasAnyOrderPermission = $this->user->hasAnyPermissionSafely($orderPermissions);
        $this->assertTrue($hasAnyOrderPermission, '用户应该至少有一个订单权限');

        // 检查是否拥有所有权限
        $hasAllOrderPermissions = $this->user->hasAnyPermissionSafely($orderPermissions, true);
        $this->assertFalse($hasAllOrderPermissions, '用户不应该拥有所有订单权限（缺少advanced_delete_orders）');

        // 检查具体权限
        $this->assertTrue($this->user->hasPermissionToSafely('advanced_view_orders_team_' . $this->team1->id));
        $this->assertTrue($this->user->hasPermissionToSafely('advanced_create_orders_team_' . $this->team1->id));
        $this->assertTrue($this->user->hasPermissionToSafely('advanced_edit_orders_team_' . $this->team1->id));
        $this->assertFalse($this->user->hasPermissionToSafely('advanced_delete_orders_team_' . $this->team1->id));
        $this->assertFalse($this->user->hasPermissionToSafely('advanced_approve_orders_team_' . $this->team1->id));
    }

    /** @test */
    public function user_can_check_permissions_across_teams()
    {
        // 在团队1分配editor角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $this->user->assignRole('editor_team_' . $this->team1->id);

        // 在团队2分配manager角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);
        $this->user->assignRole('manager_team_' . $this->team2->id);

        // 检查团队1的权限
        $hasViewInTeam1 = $this->user->hasPermissionInTeam($this->team1->id, 'advanced_view_orders_team_' . $this->team1->id);
        $this->assertTrue($hasViewInTeam1, '用户在团队1应该有查看订单权限');

        $hasDeleteInTeam1 = $this->user->hasPermissionInTeam($this->team1->id, 'advanced_delete_orders_team_' . $this->team1->id);
        $this->assertFalse($hasDeleteInTeam1, '用户在团队1不应该有删除订单权限');

        // 检查团队2的权限
        $hasViewInTeam2 = $this->user->hasPermissionInTeam($this->team2->id, 'advanced_view_orders_team_' . $this->team2->id);
        $this->assertTrue($hasViewInTeam2, '用户在团队2应该有查看订单权限');

        $hasDeleteInTeam2 = $this->user->hasPermissionInTeam($this->team2->id, 'advanced_delete_orders_team_' . $this->team2->id);
        $this->assertTrue($hasDeleteInTeam2, '用户在团队2应该有删除订单权限');

        $hasManageInTeam2 = $this->user->hasPermissionInTeam($this->team2->id, 'advanced_manage_team_team_' . $this->team2->id);
        $this->assertTrue($hasManageInTeam2, '用户在团队2应该有管理团队权限');

        // 验证权限隔离
        $hasManageInTeam1 = $this->user->hasPermissionInTeam($this->team1->id, 'advanced_manage_team_team_' . $this->team1->id);
        $this->assertFalse($hasManageInTeam1, '用户在团队1不应该有管理团队权限');
    }

    /** @test */
    public function user_can_use_team_context_switching()
    {
        // 在团队1分配editor角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $this->user->assignRole('editor_team_' . $this->team1->id);

        // 在团队2分配manager角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);
        $this->user->assignRole('manager_team_' . $this->team2->id);

        // 设置用户当前团队为权限上下文（确保有初始上下文）
        $this->user->setCurrentTeamAsPermissionContext();

        // 验证当前团队上下文（应该是团队1）
        $this->assertEquals($this->team1->id, $this->user->current_team_id);

        // 在团队2上下文中执行操作
        $result = $this->user->withTeamContext($this->team2->id, function ($user) {
            return [
                'current_team_context' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
                'has_delete_permission' => $user->hasPermissionToSafely('advanced_delete_orders_team_' . $this->team2->id),
                'has_manage_permission' => $user->hasPermissionToSafely('advanced_manage_team_team_' . $this->team2->id),
                'roles' => $user->roles->pluck('name')->toArray()
            ];
        });

        // 验证在团队2上下文中的权限
        $this->assertEquals($this->team2->id, $result['current_team_context']);
        $this->assertTrue($result['has_delete_permission'], '在团队2上下文中应该有删除权限');
        $this->assertTrue($result['has_manage_permission'], '在团队2上下文中应该有管理权限');
        $this->assertContains('manager_team_' . $this->team2->id, $result['roles'], '在团队2上下文中应该有manager角色');

        // 验证上下文已恢复到原始团队
        $currentContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
        // 注意：withTeamContext 执行完毕后，上下文应该恢复到用户的当前团队
        $this->assertEquals($this->user->current_team_id, $currentContext, '上下文应该恢复到用户当前团队');
    }

    /** @test */
    public function user_can_get_roles_in_different_teams()
    {
        // 在团队1分配editor角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $this->user->assignRole('editor_team_' . $this->team1->id);

        // 在团队2分配manager角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);
        $this->user->assignRole('manager_team_' . $this->team2->id);

        // 获取团队1的角色
        $team1Roles = $this->user->getRolesInTeam($this->team1->id);
        $this->assertEquals(1, $team1Roles->count());
        $this->assertEquals('editor_team_' . $this->team1->id, $team1Roles->first()->name);

        // 获取团队2的角色
        $team2Roles = $this->user->getRolesInTeam($this->team2->id);
        $this->assertEquals(1, $team2Roles->count());
        $this->assertEquals('manager_team_' . $this->team2->id, $team2Roles->first()->name);

        // 获取所有团队的角色
        $allRoles = $this->user->getAllRoles();
        $this->assertEquals(2, $allRoles->count());

        $roleNames = $allRoles->pluck('name')->toArray();
        $this->assertContains('editor_team_' . $this->team1->id, $roleNames);
        $this->assertContains('manager_team_' . $this->team2->id, $roleNames);
    }

    /** @test */
    public function user_can_safely_assign_and_remove_roles_across_teams()
    {
        // 在团队1分配角色
        $this->user->assignRoleInTeam($this->team1->id, 'editor_team_' . $this->team1->id);

        // 验证角色分配
        $team1Roles = $this->user->getRolesInTeam($this->team1->id);
        $this->assertEquals(1, $team1Roles->count());
        $this->assertEquals('editor_team_' . $this->team1->id, $team1Roles->first()->name);

        // 在团队2分配角色
        $this->user->assignRoleInTeam($this->team2->id, 'manager_team_' . $this->team2->id);

        // 验证角色分配
        $team2Roles = $this->user->getRolesInTeam($this->team2->id);
        $this->assertEquals(1, $team2Roles->count());
        $this->assertEquals('manager_team_' . $this->team2->id, $team2Roles->first()->name);

        // 移除团队1的角色
        $this->user->removeRoleInTeam($this->team1->id, 'editor_team_' . $this->team1->id);

        // 验证角色移除
        $team1RolesAfterRemoval = $this->user->getRolesInTeam($this->team1->id);
        $this->assertEquals(0, $team1RolesAfterRemoval->count());

        // 验证团队2的角色未受影响
        $team2RolesAfterRemoval = $this->user->getRolesInTeam($this->team2->id);
        $this->assertEquals(1, $team2RolesAfterRemoval->count());
        $this->assertEquals('manager_team_' . $this->team2->id, $team2RolesAfterRemoval->first()->name);
    }

    /** @test */
    public function user_can_sync_roles_safely()
    {
        // 在团队1分配多个角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        // 创建额外的角色
        $viewerRole = Role::create([
            'name' => 'viewer_team_' . $this->team1->id,
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $viewerRole->givePermissionTo([
            'advanced_view_orders_team_' . $this->team1->id,
            'advanced_view_products_team_' . $this->team1->id
        ]);

        // 分配多个角色
        $this->user->assignRole(['editor_team_' . $this->team1->id, 'viewer_team_' . $this->team1->id]);

        // 验证角色分配
        $this->assertTrue($this->user->hasRole('editor_team_' . $this->team1->id));
        $this->assertTrue($this->user->hasRole('viewer_team_' . $this->team1->id));

        // 同步角色（只保留editor）
        $this->user->syncRolesSafely(['editor_team_' . $this->team1->id]);

        // 验证同步结果
        $this->assertTrue($this->user->hasRole('editor_team_' . $this->team1->id));
        $this->assertFalse($this->user->hasRole('viewer_team_' . $this->team1->id));

        // 验证权限仍然正确
        $this->assertTrue($this->user->hasPermissionToSafely('advanced_view_orders_team_' . $this->team1->id));
        $this->assertTrue($this->user->hasPermissionToSafely('advanced_edit_orders_team_' . $this->team1->id));
    }

    /** @test */
    public function user_permissions_are_properly_isolated_between_teams()
    {
        // 在团队1创建特殊权限
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        $specialPermission1 = Permission::create([
            'name' => 'special_action_team1',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $editorRole = Role::findByName('editor_team_' . $this->team1->id);
        $editorRole->givePermissionTo($specialPermission1);

        // 在团队2创建不同的特殊权限
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);

        $specialPermission2 = Permission::create([
            'name' => 'special_action_team2',
            'team_id' => $this->team2->id,
            'guard_name' => 'web'
        ]);

        $managerRole = Role::findByName('manager_team_' . $this->team2->id);
        $managerRole->givePermissionTo($specialPermission2);

        // 分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $this->user->assignRole('editor_team_' . $this->team1->id);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);
        $this->user->assignRole('manager_team_' . $this->team2->id);

        // 验证权限隔离
        $hasSpecial1InTeam1 = $this->user->hasPermissionInTeam($this->team1->id, 'special_action_team1');
        $this->assertTrue($hasSpecial1InTeam1, '用户在团队1应该有special_action_team1权限');

        $hasSpecial2InTeam1 = $this->user->hasPermissionInTeam($this->team1->id, 'special_action_team2');
        $this->assertFalse($hasSpecial2InTeam1, '用户在团队1不应该有special_action_team2权限');

        $hasSpecial2InTeam2 = $this->user->hasPermissionInTeam($this->team2->id, 'special_action_team2');
        $this->assertTrue($hasSpecial2InTeam2, '用户在团队2应该有special_action_team2权限');

        $hasSpecial1InTeam2 = $this->user->hasPermissionInTeam($this->team2->id, 'special_action_team1');
        $this->assertFalse($hasSpecial1InTeam2, '用户在团队2不应该有special_action_team1权限');
    }

    /** @test */
    public function user_can_check_can_safely_method()
    {
        // 设置团队1上下文并分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $this->user->assignRole('editor_team_' . $this->team1->id);

        // 测试canSafely方法
        $this->assertTrue($this->user->canSafely('advanced_view_orders_team_' . $this->team1->id), '用户应该可以查看订单');
        $this->assertTrue($this->user->canSafely('advanced_edit_orders_team_' . $this->team1->id), '用户应该可以编辑订单');
        $this->assertFalse($this->user->canSafely('advanced_delete_orders_team_' . $this->team1->id), '用户不应该可以删除订单');
        $this->assertFalse($this->user->canSafely('advanced_approve_orders_team_' . $this->team1->id), '用户不应该可以审批订单');

        // 测试不存在的权限
        $this->assertFalse($this->user->canSafely('nonexistent_permission'), '不存在的权限应该返回false');
    }

    /** @test */
    public function user_can_set_current_team_as_permission_context()
    {
        // 在团队1分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $this->user->assignRole('editor_team_' . $this->team1->id);

        // 切换到团队2
        $this->user->current_team_id = $this->team2->id;
        $this->user->save();

        // 在团队2分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);
        $this->user->assignRole('manager_team_' . $this->team2->id);

        // 设置当前团队为权限上下文
        $this->user->setCurrentTeamAsPermissionContext();

        // 验证权限上下文已设置为当前团队
        $currentContext = app(PermissionRegistrar::class)->getPermissionsTeamId();
        $this->assertEquals($this->team2->id, $currentContext);

        // 验证权限检查使用正确的上下文
        $this->assertTrue($this->user->hasPermissionToSafely('advanced_delete_orders_team_' . $this->team2->id), '在团队2上下文中应该有删除权限');
        $this->assertTrue($this->user->hasPermissionToSafely('advanced_manage_team_team_' . $this->team2->id), '在团队2上下文中应该有管理权限');
    }
}
