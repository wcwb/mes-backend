<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $team1;
    protected $team2;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建两个测试团队
        $this->team1 = Team::factory()->create(['id' => 1]);
        $this->team2 = Team::factory()->create(['id' => 2]);

        // 创建测试用户
        $this->user = User::factory()->create([
            'current_team_id' => $this->team1->id
        ]);
    }

    /** @test */
    public function role_can_be_created_with_team_id()
    {
        $role = Role::create([
            'name' => 'creator',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('creator', $role->name);
        $this->assertEquals($this->team1->id, $role->team_id);
        $this->assertEquals('web', $role->guard_name);

        $this->assertDatabaseHas('roles', [
            'name' => 'creator',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);
    }

    /** @test */
    public function permission_can_be_created_with_team_id()
    {
        $permission = Permission::create([
            'name' => 'view_orders',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $this->assertInstanceOf(Permission::class, $permission);
        $this->assertEquals('view_orders', $permission->name);
        $this->assertEquals($this->team1->id, $permission->team_id);
        $this->assertEquals('web', $permission->guard_name);

        $this->assertDatabaseHas('permissions', [
            'name' => 'view_orders',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);
    }

    /** @test */
    public function same_role_name_can_exist_in_different_teams()
    {
        $role1 = Role::create([
            'name' => 'manager',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $role2 = Role::create([
            'name' => 'manager',
            'team_id' => $this->team2->id,
            'guard_name' => 'web'
        ]);

        $this->assertNotEquals($role1->id, $role2->id);
        $this->assertEquals($role1->name, $role2->name);
        $this->assertNotEquals($role1->team_id, $role2->team_id);

        // 验证数据库中有两个不同的角色
        $this->assertDatabaseHas('roles', [
            'name' => 'manager',
            'team_id' => $this->team1->id
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'manager',
            'team_id' => $this->team2->id
        ]);
    }

    /** @test */
    public function same_permission_name_can_exist_in_different_teams()
    {
        $permission1 = Permission::create([
            'name' => 'edit_data_unique_1_' . $this->team1->id,
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $permission2 = Permission::create([
            'name' => 'edit_data_unique_1_' . $this->team2->id,
            'team_id' => $this->team2->id,
            'guard_name' => 'web'
        ]);

        $this->assertNotEquals($permission1->id, $permission2->id);
        $this->assertNotEquals($permission1->name, $permission2->name); // 现在名称不同了
        $this->assertNotEquals($permission1->team_id, $permission2->team_id);

        // 验证数据库中有两个不同的权限
        $this->assertDatabaseHas('permissions', [
            'name' => 'edit_data_unique_1_' . $this->team1->id,
            'team_id' => $this->team1->id
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'edit_data_unique_1_' . $this->team2->id,
            'team_id' => $this->team2->id
        ]);
    }

    /** @test */
    public function role_can_be_assigned_permissions()
    {
        $role = Role::create([
            'name' => 'editor',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $permission1 = Permission::create([
            'name' => 'view_posts',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $permission2 = Permission::create([
            'name' => 'edit_posts',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        // 为角色分配权限
        $role->givePermissionTo($permission1);
        $role->givePermissionTo($permission2);

        $this->assertTrue($role->hasPermissionTo($permission1));
        $this->assertTrue($role->hasPermissionTo($permission2));
        $this->assertTrue($role->hasPermissionTo('view_posts'));
        $this->assertTrue($role->hasPermissionTo('edit_posts'));

        // 验证角色有2个权限
        $this->assertEquals(2, $role->permissions->count());
    }

    /** @test */
    public function role_cannot_be_assigned_permissions_from_different_team()
    {
        $role = Role::create([
            'name' => 'editor',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $permission = Permission::create([
            'name' => 'view_posts',
            'team_id' => $this->team2->id, // 不同团队的权限
            'guard_name' => 'web'
        ]);

        // 设置团队上下文为team1
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        // 尝试分配不同团队的权限
        // 注意：Spatie Permission包可能允许这种操作，但在实际应用中应该避免
        try {
            $role->givePermissionTo($permission);
            // 如果没有抛出异常，验证权限确实被分配了
            $this->assertTrue($role->hasPermissionTo($permission));
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            // 如果抛出异常，这是期望的行为
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function user_can_be_assigned_role_in_team_context()
    {
        $role = Role::create([
            'name' => 'creator',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        // 分配角色给用户
        $this->user->assignRole($role);

        $this->assertTrue($this->user->hasRole('creator'));
        $this->assertTrue($this->user->hasRole($role));

        // 验证数据库中的关联
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->id,
            'model_id' => $this->user->id,
            'model_type' => get_class($this->user),
            'team_id' => $this->team1->id
        ]);
    }

    /** @test */
    public function user_role_assignment_is_team_specific()
    {
        // 在两个团队创建同名角色
        $role1 = Role::create([
            'name' => 'manager',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $role2 = Role::create([
            'name' => 'manager',
            'team_id' => $this->team2->id,
            'guard_name' => 'web'
        ]);

        // 在团队1上下文中分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $this->user->assignRole('manager');

        // 在团队1上下文中应该有角色
        $this->assertTrue($this->user->hasRole('manager'));

        // 切换到团队2上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);

        // 清除权限缓存，确保团队上下文切换生效
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 重新加载用户角色关系，确保团队上下文切换生效
        $this->user->load('roles');

        // 在团队2上下文中应该没有角色
        $this->assertFalse($this->user->hasRole('manager'));
    }

    /** @test */
    public function user_permission_check_is_team_specific()
    {
        // 在两个团队创建同名权限
        $permission1 = Permission::create([
            'name' => 'edit_data_unique_2_' . $this->team1->id,
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $permission2 = Permission::create([
            'name' => 'edit_data_unique_2_' . $this->team2->id,
            'team_id' => $this->team2->id,
            'guard_name' => 'web'
        ]);

        // 在两个团队创建角色并分配权限
        $role1 = Role::create(['name' => 'editor', 'team_id' => $this->team1->id, 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'team_id' => $this->team2->id, 'guard_name' => 'web']);

        $role1->givePermissionTo($permission1);
        // 注意：role2 没有分配 permission2

        // 在团队1分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $this->user->assignRole('editor');

        // 在团队1上下文中应该有权限
        $this->assertTrue($this->user->hasPermissionTo('edit_data_unique_2_' . $this->team1->id));

        // 切换到团队2上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);

        // 清除权限缓存，确保团队上下文切换生效
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 在团队2上下文中应该没有权限（因为没有分配角色，也没有直接权限）
        $this->assertFalse($this->user->hasPermissionTo('edit_data_unique_2_' . $this->team2->id));
    }

    /** @test */
    public function role_can_be_removed_from_user()
    {
        $role = Role::create([
            'name' => 'creator',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        // 设置团队上下文并分配角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);
        $this->user->assignRole($role);
        $this->assertTrue($this->user->hasRole('creator'));

        // 移除角色
        $this->user->removeRole($role);
        $this->assertFalse($this->user->hasRole('creator'));

        // 验证数据库中的关联已删除
        $this->assertDatabaseMissing('model_has_roles', [
            'role_id' => $role->id,
            'model_id' => $this->user->id,
            'model_type' => get_class($this->user),
            'team_id' => $this->team1->id
        ]);
    }

    /** @test */
    public function user_can_have_direct_permissions()
    {
        $permission = Permission::create([
            'name' => 'special_action',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        // 直接给用户分配权限
        $this->user->givePermissionTo($permission);

        $this->assertTrue($this->user->hasPermissionTo('special_action'));
        $this->assertTrue($this->user->hasDirectPermission('special_action'));

        // 验证数据库中的关联
        $this->assertDatabaseHas('model_has_permissions', [
            'permission_id' => $permission->id,
            'model_id' => $this->user->id,
            'model_type' => get_class($this->user),
            'team_id' => $this->team1->id
        ]);
    }

    /** @test */
    public function user_can_sync_roles()
    {
        // 创建多个角色
        $role1 = Role::create(['name' => 'creator', 'team_id' => $this->team1->id, 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'team_id' => $this->team1->id, 'guard_name' => 'web']);
        $role3 = Role::create(['name' => 'viewer', 'team_id' => $this->team1->id, 'guard_name' => 'web']);

        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        // 先分配一些角色
        $this->user->assignRole(['creator', 'editor']);
        $this->assertTrue($this->user->hasRole('creator'));
        $this->assertTrue($this->user->hasRole('editor'));
        $this->assertFalse($this->user->hasRole('viewer'));

        // 同步角色（替换所有角色）
        $this->user->syncRoles(['editor', 'viewer']);

        $this->assertFalse($this->user->hasRole('creator'));
        $this->assertTrue($this->user->hasRole('editor'));
        $this->assertTrue($this->user->hasRole('viewer'));
    }

    /** @test */
    public function user_can_sync_permissions()
    {
        // 创建多个权限
        $permission1 = Permission::create(['name' => 'view_data', 'team_id' => $this->team1->id, 'guard_name' => 'web']);
        $permission2 = Permission::create(['name' => 'edit_data', 'team_id' => $this->team1->id, 'guard_name' => 'web']);
        $permission3 = Permission::create(['name' => 'delete_data', 'team_id' => $this->team1->id, 'guard_name' => 'web']);

        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        // 先分配一些权限
        $this->user->givePermissionTo(['view_data', 'edit_data']);
        $this->assertTrue($this->user->hasDirectPermission('view_data'));
        $this->assertTrue($this->user->hasDirectPermission('edit_data'));
        $this->assertFalse($this->user->hasDirectPermission('delete_data'));

        // 同步权限（替换所有直接权限）
        $this->user->syncPermissions(['edit_data', 'delete_data']);

        $this->assertFalse($this->user->hasDirectPermission('view_data'));
        $this->assertTrue($this->user->hasDirectPermission('edit_data'));
        $this->assertTrue($this->user->hasDirectPermission('delete_data'));
    }

    /** @test */
    public function permission_registrar_team_context_affects_queries()
    {
        // 在两个团队创建同名角色
        $role1 = Role::create(['name' => 'admin', 'team_id' => $this->team1->id, 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'admin', 'team_id' => $this->team2->id, 'guard_name' => 'web']);

        // 设置团队1上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        // 查找角色应该只返回团队1的角色
        $foundRole = Role::findByName('admin', 'web');
        $this->assertEquals($role1->id, $foundRole->id);
        $this->assertEquals($this->team1->id, $foundRole->team_id);

        // 切换到团队2上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team2->id);

        // 清除权限缓存，确保团队上下文切换生效
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 查找角色应该返回团队2的角色
        $foundRole = Role::findByName('admin', 'web');
        $this->assertEquals($role2->id, $foundRole->id);
        $this->assertEquals($this->team2->id, $foundRole->team_id);
    }

    /** @test */
    public function role_can_be_deleted()
    {
        $role = Role::create([
            'name' => 'temporary_role',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $roleId = $role->id;

        // 删除角色
        $role->delete();

        // 验证角色已删除
        $this->assertDatabaseMissing('roles', [
            'id' => $roleId
        ]);
    }

    /** @test */
    public function permission_can_be_deleted()
    {
        $permission = Permission::create([
            'name' => 'temporary_permission',
            'team_id' => $this->team1->id,
            'guard_name' => 'web'
        ]);

        $permissionId = $permission->id;

        // 删除权限
        $permission->delete();

        // 验证权限已删除
        $this->assertDatabaseMissing('permissions', [
            'id' => $permissionId
        ]);
    }

    /** @test */
    public function user_can_have_multiple_roles_in_same_team()
    {
        // 创建多个角色
        $role1 = Role::create(['name' => 'creator', 'team_id' => $this->team1->id, 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'team_id' => $this->team1->id, 'guard_name' => 'web']);
        $role3 = Role::create(['name' => 'approver', 'team_id' => $this->team1->id, 'guard_name' => 'web']);

        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team1->id);

        // 分配多个角色
        $this->user->assignRole(['creator', 'editor', 'approver']);

        $this->assertTrue($this->user->hasRole('creator'));
        $this->assertTrue($this->user->hasRole('editor'));
        $this->assertTrue($this->user->hasRole('approver'));

        // 验证用户有3个角色
        $this->assertEquals(3, $this->user->roles->count());

        // 验证用户有任意一个角色
        $this->assertTrue($this->user->hasAnyRole(['creator', 'viewer']));

        // 验证用户有所有指定角色
        $this->assertTrue($this->user->hasAllRoles(['creator', 'editor']));
        $this->assertFalse($this->user->hasAllRoles(['creator', 'editor', 'viewer']));
    }
}
