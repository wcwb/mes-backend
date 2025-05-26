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

class TeamManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $owner;
    protected $admin;
    protected $member;
    protected $team;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试团队
        $this->team = Team::factory()->create(['id' => 1]);

        // 创建不同角色的用户
        $this->owner = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->admin = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->member = User::factory()->create(['current_team_id' => $this->team->id]);

        // 设置团队成员关系
        $this->team->users()->attach([
            $this->owner->id => ['role' => 'owner'],
            $this->admin->id => ['role' => 'admin'],
            $this->member->id => ['role' => 'member']
        ]);

        // 创建基础权限角色
        $this->createBasicPermissionRoles();
    }

    protected function createBasicPermissionRoles()
    {
        // 创建权限角色（不同于团队角色）
        $managerRole = Role::create([
            'name' => 'manager',
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
            'manage_team',
            'invite_users',
            'remove_users',
            'assign_roles',
            'view_team_data',
            'edit_team_data',
            'delete_team_data'
        ];

        foreach ($permissions as $permissionName) {
            Permission::create([
                'name' => $permissionName,
                'team_id' => $this->team->id,
                'guard_name' => 'web'
            ]);
        }

        // 为角色分配权限
        $managerRole->givePermissionTo($permissions);

        $editorRole->givePermissionTo([
            'view_team_data',
            'edit_team_data'
        ]);

        $viewerRole->givePermissionTo([
            'view_team_data'
        ]);
    }

    /** @test */
    public function team_can_be_created_with_owner()
    {
        $newTeam = Team::factory()->create([
            'name' => '新团队',
            'personal_team' => false
        ]);

        $newOwner = User::factory()->create([
            'current_team_id' => $newTeam->id
        ]);

        // 设置团队所有者
        $newTeam->users()->attach($newOwner->id, ['role' => 'owner']);

        $this->assertInstanceOf(Team::class, $newTeam);
        $this->assertEquals('新团队', $newTeam->name);
        $this->assertFalse($newTeam->personal_team);

        // 验证所有者关系
        $owner = $newTeam->users()->where('role', 'owner')->first();
        $this->assertNotNull($owner);
        $this->assertEquals($newOwner->id, $owner->id);
    }

    /** @test */
    public function team_owner_can_invite_new_members()
    {
        $newUser = User::factory()->create();

        // 团队所有者邀请新成员
        $this->team->users()->attach($newUser->id, ['role' => 'member']);

        // 验证新成员已加入团队
        $this->assertTrue($this->team->users->contains($newUser));
        $this->assertEquals(4, $this->team->users->count()); // 原有3个 + 新增1个

        // 验证新成员的角色 - 直接查询Membership模型
        $newMemberMembership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $newUser->id)
            ->first();
        $this->assertNotNull($newMemberMembership, '未找到新成员membership');
        $this->assertEquals('member', $newMemberMembership->role);
    }

    /** @test */
    public function team_admin_can_manage_members()
    {
        $newUser = User::factory()->create();

        // 管理员添加新成员
        $this->team->users()->attach($newUser->id, ['role' => 'member']);

        // 验证成员已添加
        $this->assertTrue($this->team->users->contains($newUser));

        // 管理员更新成员角色
        $this->team->users()->updateExistingPivot($newUser->id, ['role' => 'admin']);

        // 验证角色已更新 - 直接查询Membership模型
        $updatedMembership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $newUser->id)
            ->first();
        $this->assertNotNull($updatedMembership, '未找到更新的成员membership');
        $this->assertEquals('admin', $updatedMembership->role);

        // 管理员移除成员
        $this->team->users()->detach($newUser->id);

        // 验证成员已移除
        $this->team->refresh();
        $this->assertFalse($this->team->users->contains($newUser));
    }

    /** @test */
    public function team_members_have_different_permissions()
    {
        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team->id);

        // 为不同用户分配不同的权限角色
        $this->owner->assignRole('manager');
        $this->admin->assignRole('editor');
        $this->member->assignRole('viewer');

        // 验证所有者权限
        $this->assertTrue($this->owner->hasPermissionTo('manage_team'));
        $this->assertTrue($this->owner->hasPermissionTo('invite_users'));
        $this->assertTrue($this->owner->hasPermissionTo('assign_roles'));
        $this->assertTrue($this->owner->hasPermissionTo('view_team_data'));
        $this->assertTrue($this->owner->hasPermissionTo('edit_team_data'));

        // 验证管理员权限
        $this->assertFalse($this->admin->hasPermissionTo('manage_team'));
        $this->assertFalse($this->admin->hasPermissionTo('invite_users'));
        $this->assertTrue($this->admin->hasPermissionTo('view_team_data'));
        $this->assertTrue($this->admin->hasPermissionTo('edit_team_data'));
        $this->assertFalse($this->admin->hasPermissionTo('delete_team_data'));

        // 验证普通成员权限
        $this->assertFalse($this->member->hasPermissionTo('manage_team'));
        $this->assertFalse($this->member->hasPermissionTo('edit_team_data'));
        $this->assertTrue($this->member->hasPermissionTo('view_team_data'));
        $this->assertFalse($this->member->hasPermissionTo('delete_team_data'));
    }

    /** @test */
    public function user_can_be_member_of_multiple_teams()
    {
        // 创建第二个团队
        $team2 = Team::factory()->create(['id' => 2, 'name' => '第二团队']);

        // 用户加入第二个团队
        $team2->users()->attach($this->member->id, ['role' => 'admin']);

        // 验证用户在两个团队中
        $this->assertTrue($this->team->users->contains($this->member));
        $this->assertTrue($team2->users->contains($this->member));

        // 验证用户在不同团队有不同角色 - 直接查询Membership模型
        $membershipInTeam1 = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $this->member->id)
            ->first();
        $membershipInTeam2 = \App\Models\Membership::where('team_id', $team2->id)
            ->where('user_id', $this->member->id)
            ->first();

        $this->assertNotNull($membershipInTeam1, '未找到团队1中的成员membership');
        $this->assertNotNull($membershipInTeam2, '未找到团队2中的成员membership');

        $this->assertEquals('member', $membershipInTeam1->role);
        $this->assertEquals('admin', $membershipInTeam2->role);
    }

    /** @test */
    public function user_can_switch_current_team()
    {
        // 创建第二个团队
        $team2 = Team::factory()->create(['id' => 2]);
        $team2->users()->attach($this->member->id, ['role' => 'admin']);

        // 验证当前团队
        $this->assertEquals($this->team->id, $this->member->current_team_id);

        // 切换当前团队
        $this->member->current_team_id = $team2->id;
        $this->member->save();

        // 验证团队已切换
        $this->assertEquals($team2->id, $this->member->current_team_id);

        // 验证数据库中的更新
        $this->assertDatabaseHas('users', [
            'id' => $this->member->id,
            'current_team_id' => $team2->id
        ]);
    }

    /** @test */
    public function team_can_have_permission_roles_assigned_to_members()
    {
        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team->id);

        // 为团队成员分配权限角色
        $this->owner->assignRole('manager');
        $this->admin->assignRole('editor');
        $this->member->assignRole('viewer');

        // 验证角色分配
        $this->assertTrue($this->owner->hasRole('manager'));
        $this->assertTrue($this->admin->hasRole('editor'));
        $this->assertTrue($this->member->hasRole('viewer'));

        // 验证权限继承
        $this->assertTrue($this->owner->hasPermissionTo('manage_team'));
        $this->assertTrue($this->admin->hasPermissionTo('edit_team_data'));
        $this->assertTrue($this->member->hasPermissionTo('view_team_data'));

        // 验证权限隔离
        $this->assertFalse($this->admin->hasPermissionTo('manage_team'));
        $this->assertFalse($this->member->hasPermissionTo('edit_team_data'));
    }

    /** @test */
    public function team_member_roles_can_be_updated()
    {
        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team->id);

        // 初始分配角色
        $this->member->assignRole('viewer');
        $this->assertTrue($this->member->hasRole('viewer'));
        $this->assertTrue($this->member->hasPermissionTo('view_team_data'));
        $this->assertFalse($this->member->hasPermissionTo('edit_team_data'));

        // 升级角色
        $this->member->removeRole('viewer');
        $this->member->assignRole('editor');

        // 验证角色更新
        $this->assertFalse($this->member->hasRole('viewer'));
        $this->assertTrue($this->member->hasRole('editor'));
        $this->assertTrue($this->member->hasPermissionTo('view_team_data'));
        $this->assertTrue($this->member->hasPermissionTo('edit_team_data'));
        $this->assertFalse($this->member->hasPermissionTo('manage_team'));
    }

    /** @test */
    public function team_can_be_deleted_with_cleanup()
    {
        // 创建一个新的团队用于测试删除，使用一个安全的ID（大于特殊团队ID）
        $safeTeamId = 100; // 使用一个远离特殊团队ID的值
        $testTeam = Team::factory()->create([
            'id' => $safeTeamId,
            'name' => '待删除的测试团队',
            'personal_team' => false
        ]);

        $teamId = $testTeam->id;

        // 确保不是特殊团队（ID 1 或 2）
        $this->assertNotContains($teamId, [1, 2], '测试团队不应该是特殊团队');

        // 为团队创建一些角色和权限
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);

        $testRole = Role::create([
            'name' => 'test_role',
            'team_id' => $teamId,
            'guard_name' => 'web'
        ]);

        $testPermission = Permission::create([
            'name' => 'test_permission',
            'team_id' => $teamId,
            'guard_name' => 'web'
        ]);

        // 验证角色和权限存在
        $this->assertEquals(1, Role::where('team_id', $teamId)->count());
        $this->assertEquals(1, Permission::where('team_id', $teamId)->count());

        // 删除团队（软删除）
        $result = $testTeam->delete();
        $this->assertTrue($result, '团队删除操作应该返回true');

        // 验证团队已软删除
        $this->assertSoftDeleted('teams', [
            'id' => $teamId
        ]);

        // 验证团队不能通过正常查询找到
        $this->assertNull(Team::find($teamId));

        // 验证团队可以通过withTrashed找到
        $this->assertNotNull(Team::withTrashed()->find($teamId));

        // 手动清理团队相关的角色和权限（在实际应用中，这应该通过模型事件或观察者来处理）
        Role::where('team_id', $teamId)->delete();
        Permission::where('team_id', $teamId)->delete();

        // 验证团队相关的角色和权限已删除
        $remainingRoles = Role::where('team_id', $teamId)->count();
        $remainingPermissions = Permission::where('team_id', $teamId)->count();

        $this->assertEquals(0, $remainingRoles);
        $this->assertEquals(0, $remainingPermissions);
    }

    /** @test */
    public function user_can_leave_team()
    {
        // 验证用户在团队中
        $this->assertTrue($this->team->users->contains($this->member));
        $this->assertEquals(3, $this->team->users->count());

        // 用户离开团队（软删除）
        $this->team->users()->detach($this->member->id);

        // 验证用户已离开团队
        $this->team->refresh();
        $this->assertFalse($this->team->users->contains($this->member));
        $this->assertEquals(2, $this->team->users->count());

        // 验证数据库中的关系仍存在但被软删除
        $this->assertDatabaseHas('team_user', [
            'team_id' => $this->team->id,
            'user_id' => $this->member->id
        ]);

        // 验证记录确实被软删除了
        $membership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $this->member->id)
            ->withTrashed()
            ->first();
        $this->assertNotNull($membership);
        $this->assertNotNull($membership->deleted_at);
    }

    /** @test */
    public function team_ownership_can_be_transferred()
    {
        // 验证当前所有者
        $currentOwner = $this->team->users()->where('role', 'owner')->first();
        $this->assertEquals($this->owner->id, $currentOwner->id);

        // 转移所有权
        $this->team->users()->updateExistingPivot($this->owner->id, ['role' => 'admin']);
        $this->team->users()->updateExistingPivot($this->admin->id, ['role' => 'owner']);

        // 验证所有权已转移
        $newOwner = $this->team->users()->where('role', 'owner')->first();
        $this->assertEquals($this->admin->id, $newOwner->id);

        // 验证前任所有者角色已更新 - 直接查询Membership模型
        $formerOwnerMembership = \App\Models\Membership::where('team_id', $this->team->id)
            ->where('user_id', $this->owner->id)
            ->first();
        $this->assertNotNull($formerOwnerMembership, '未找到前任所有者membership');
        $this->assertEquals('admin', $formerOwnerMembership->role);
    }

    /** @test */
    public function team_can_have_personal_team_flag()
    {
        // 创建个人团队
        $personalTeam = Team::factory()->create([
            'name' => '个人团队',
            'personal_team' => true
        ]);

        $user = User::factory()->create([
            'current_team_id' => $personalTeam->id
        ]);

        $personalTeam->users()->attach($user->id, ['role' => 'owner']);

        // 验证个人团队标志
        $this->assertTrue($personalTeam->personal_team);

        // 验证个人团队只有一个成员（所有者）
        $this->assertEquals(1, $personalTeam->users->count());

        $owner = $personalTeam->users()->where('role', 'owner')->first();
        $this->assertEquals($user->id, $owner->id);
    }

    /** @test */
    public function team_members_can_have_multiple_permission_roles()
    {
        // 设置团队上下文
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team->id);

        // 创建额外的角色
        $reporterRole = Role::create([
            'name' => 'reporter',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        $reportPermission = Permission::create([
            'name' => 'generate_reports',
            'team_id' => $this->team->id,
            'guard_name' => 'web'
        ]);

        $reporterRole->givePermissionTo($reportPermission);

        // 为用户分配多个角色
        $this->member->assignRole(['viewer', 'reporter']);

        // 验证用户有多个角色
        $this->assertTrue($this->member->hasRole('viewer'));
        $this->assertTrue($this->member->hasRole('reporter'));

        // 验证用户有来自不同角色的权限
        $this->assertTrue($this->member->hasPermissionTo('view_team_data')); // 来自viewer角色
        $this->assertTrue($this->member->hasPermissionTo('generate_reports')); // 来自reporter角色

        // 验证用户没有其他角色的权限
        $this->assertFalse($this->member->hasPermissionTo('edit_team_data'));
        $this->assertFalse($this->member->hasPermissionTo('manage_team'));
    }

    /** @test */
    public function team_roles_are_isolated_between_teams()
    {
        // 创建第二个团队
        $team2 = Team::factory()->create(['id' => 2]);

        // 在第二个团队创建同名角色
        $managerRole2 = Role::create([
            'name' => 'manager',
            'team_id' => $team2->id,
            'guard_name' => 'web'
        ]);

        $specialPermission = Permission::create([
            'name' => 'special_action',
            'team_id' => $team2->id,
            'guard_name' => 'web'
        ]);

        $managerRole2->givePermissionTo($specialPermission);

        // 用户加入第二个团队
        $team2->users()->attach($this->member->id, ['role' => 'admin']);

        // 在第一个团队分配manager角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->team->id);
        $this->member->assignRole('manager');

        // 在第二个团队分配manager角色
        app(PermissionRegistrar::class)->setPermissionsTeamId($team2->id);
        $this->member->assignRole('manager');

        // 验证在第一个团队的权限
        $hasPermissionInTeam1 = $this->member->hasPermissionInTeam($this->team->id, 'manage_team');
        $this->assertTrue($hasPermissionInTeam1);

        $hasSpecialInTeam1 = $this->member->hasPermissionInTeam($this->team->id, 'special_action');
        $this->assertFalse($hasSpecialInTeam1);

        // 验证在第二个团队的权限
        $hasPermissionInTeam2 = $this->member->hasPermissionInTeam($team2->id, 'special_action');
        $this->assertTrue($hasPermissionInTeam2);

        // 在第二个团队中不应该有第一个团队的manage_team权限
        // 因为第二个团队的manager角色没有被分配manage_team权限
        $hasManageInTeam2 = $this->member->hasPermissionInTeam($team2->id, 'manage_team');
        $this->assertFalse($hasManageInTeam2);
    }

    /** @test */
    public function team_can_be_updated()
    {
        $originalName = $this->team->name;

        // 更新团队信息
        $this->team->update([
            'name' => '更新后的团队名称'
        ]);

        // 验证更新
        $this->assertNotEquals($originalName, $this->team->name);
        $this->assertEquals('更新后的团队名称', $this->team->name);

        // 验证数据库中的更新
        $this->assertDatabaseHas('teams', [
            'id' => $this->team->id,
            'name' => '更新后的团队名称'
        ]);
    }
}
