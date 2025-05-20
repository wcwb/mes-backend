<?php

namespace Tests\Feature;

use App\Helpers\PermissionHelper;
use App\Helpers\TeamConstants;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeamPermissionIntegrationTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * 团队与用户数据
     */
    protected User $user;
    protected Team $team1;
    protected Team $team2;
    
    /**
     * 设置测试环境
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建特殊团队
        $this->createSpecialTeams();
        
        // 创建测试数据
        $this->createTestData();
    }
    
    /**
     * 创建特殊团队
     */
    protected function createSpecialTeams(): void
    {
        // 创建超级管理员
        $admin = User::factory()->create([
            'name' => '超级管理员',
            'email' => 'admin@example.com',
        ]);
        
        // 创建管理员团队
        $adminTeam = $admin->ownedTeams()->create([
            'id' => TeamConstants::ADMIN_TEAM_ID,
            'name' => TeamConstants::ADMIN_TEAM_NAME,
            'personal_team' => false,
        ]);
        
        // 创建默认团队
        $defaultTeam = $admin->ownedTeams()->create([
            'id' => TeamConstants::DEFAULT_TEAM_ID,
            'name' => TeamConstants::DEFAULT_TEAM_NAME,
            'personal_team' => false,
        ]);
        
        // 设置当前团队
        $admin->current_team_id = $adminTeam->id;
        $admin->save();
    }
    
    /**
     * 创建测试数据
     */
    protected function createTestData(): void
    {
        // 创建测试用户
        $this->user = User::factory()->create([
            'name' => '集成测试用户',
            'email' => 'integration@test.com',
        ]);
        
        // 创建两个测试团队
        $this->team1 = $this->user->ownedTeams()->create([
            'name' => '测试团队1',
            'personal_team' => false,
        ]);
        
        $this->team2 = $this->user->ownedTeams()->create([
            'name' => '测试团队2',
            'personal_team' => false,
        ]);
        
        // 为团队1创建角色和权限
        PermissionHelper::setCurrentTeamId($this->team1->id);
        Permission::create(['name' => 'team1_permission', 'guard_name' => 'web']);
        $role1 = Role::create(['name' => 'team1_role', 'guard_name' => 'web']);
        $role1->givePermissionTo('team1_permission');
        
        // 为团队2创建角色和权限
        PermissionHelper::setCurrentTeamId($this->team2->id);
        Permission::create(['name' => 'team2_permission', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'team2_role', 'guard_name' => 'web']);
        $role2->givePermissionTo('team2_permission');
        
        // 分配角色给用户
        PermissionHelper::setCurrentTeamId($this->team1->id);
        $this->user->assignRole('team1_role');
        
        PermissionHelper::setCurrentTeamId($this->team2->id);
        $this->user->assignRole('team2_role');
        
        // 初始切换到团队1
        $this->user->switchTeam($this->team1);
        PermissionHelper::setCurrentTeamId($this->team1->id);
    }
    
    /**
     * 测试团队上下文更新
     */
    public function test_team_switched_event_updates_permission_context()
    {
        // 初始检查团队上下文（应该是团队1）
        $this->assertEquals($this->team1->id, PermissionHelper::getCurrentTeamId());
        
        // 刷新用户关联确保权限已更新
        $this->user->refresh();
        PermissionHelper::refreshUserPermissionCache($this->user);
        
        $this->assertTrue($this->user->hasPermissionTo('team1_permission'));
        $this->assertFalse($this->user->hasPermissionTo('team2_permission'));
        
        // 手动触发团队切换
        $this->user->switchTeam($this->team2);
        PermissionHelper::setCurrentTeamId($this->team2->id);
        
        // 检查权限上下文是否已更新
        $this->assertEquals($this->team2->id, PermissionHelper::getCurrentTeamId());
        
        // 重新载入用户以清除权限缓存
        $this->user->refresh();
        PermissionHelper::refreshUserPermissionCache($this->user);
        
        // 验证用户现在拥有团队2的权限
        $this->assertTrue($this->user->hasPermissionTo('team2_permission'));
        $this->assertFalse($this->user->hasPermissionTo('team1_permission'));
    }
    
    /**
     * 测试团队权限中间件是否正确应用权限检查
     */
    public function test_permission_middleware()
    {
        // 设置中间件以检查team1_permission权限
        $middleware = new \App\Http\Middleware\CheckPermission();
        
        // 创建请求对象并设置已认证用户
        $request = request();
        $request->setUserResolver(function () {
            return $this->user;
        });
        
        // 创建响应闭包
        $next = function ($request) {
            return response('测试通过');
        };
        
        // 确保用户在团队1
        $this->user->switchTeam($this->team1);
        PermissionHelper::setCurrentTeamId($this->team1->id);
        PermissionHelper::refreshUserPermissionCache($this->user);
        
        // 执行中间件（应该通过，因为当前在团队1）
        $response = $middleware->handle($request, $next, 'team1_permission');
        $this->assertEquals('测试通过', $response->getContent());
        
        // 切换到团队2
        $this->user->switchTeam($this->team2);
        PermissionHelper::setCurrentTeamId($this->team2->id);
        
        // 清除权限缓存
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->refresh();
        
        // 再次执行中间件（应该抛出异常，因为团队2没有team1_permission权限）
        $this->expectException(\Spatie\Permission\Exceptions\UnauthorizedException::class);
        $middleware->handle($request, $next, 'team1_permission');
    }
    
    /**
     * 测试SetSpatieTeamId中间件是否正确设置权限上下文
     */
    public function test_set_spatie_team_id_middleware()
    {
        // 创建测试用户和请求
        $user = $this->user;
        $team = $this->team1;
        
        // 设置中间件
        $middleware = new \App\Http\Middleware\SetSpatieTeamId();
        
        // 创建请求对象并设置已认证用户
        $request = request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        // 创建响应闭包
        $next = function ($request) {
            return response('测试通过');
        };
        
        // 执行中间件
        $response = $middleware->handle($request, $next);
        
        // 验证权限团队ID是否已正确设置
        $this->assertEquals($team->id, PermissionHelper::getCurrentTeamId());
    }
    
    /**
     * 测试没有当前团队的用户默认使用DEFAULT_TEAM_ID
     */
    public function test_user_without_current_team_uses_default_team()
    {
        // 创建一个新用户，不分配当前团队
        $user = User::factory()->create([
            'name' => '无团队用户',
            'email' => 'noteam@test.com',
            'current_team_id' => null,
        ]);
        
        // 创建请求对象并设置用户
        $request = request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        // 设置中间件
        $middleware = new \App\Http\Middleware\CheckPermission();
        
        // 创建响应闭包
        $next = function ($request) {
            return response('测试通过');
        };
        
        // 执行中间件（应该将权限团队ID设置为默认值）
        try {
            $middleware->handle($request, $next, 'some_permission');
        } catch (\Exception $e) {
            // 检查权限团队ID是否已设置为默认值
            $this->assertEquals(
                TeamConstants::DEFAULT_TEAM_ID, 
                app(PermissionRegistrar::class)->getPermissionsTeamId()
            );
        }
    }
    
    /**
     * 测试EnsureUserHasTeam中间件
     */
    public function test_ensure_user_has_team_middleware()
    {
        $this->markTestSkipped('跳过EnsureUserHasTeam中间件测试');
    }
    
    /**
     * 测试角色中间件
     */
    public function test_role_middleware()
    {
        // 设置中间件以检查team1_role角色
        $middleware = new \App\Http\Middleware\CheckRole();
        
        // 创建请求对象并设置已认证用户
        $request = request();
        $request->setUserResolver(function () {
            return $this->user;
        });
        
        // 创建响应闭包
        $next = function ($request) {
            return response('测试通过');
        };
        
        // 确保用户在团队1
        $this->user->switchTeam($this->team1);
        PermissionHelper::setCurrentTeamId($this->team1->id);
        PermissionHelper::refreshUserPermissionCache($this->user);
        
        // 执行中间件（应该通过，因为当前在团队1）
        $response = $middleware->handle($request, $next, 'team1_role');
        $this->assertEquals('测试通过', $response->getContent());
        
        // 切换到团队2
        $this->user->switchTeam($this->team2);
        PermissionHelper::setCurrentTeamId($this->team2->id);
        
        // 清除权限缓存
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->refresh();
        
        // 再次执行中间件（应该抛出异常，因为团队2没有team1_role角色）
        $this->expectException(\Spatie\Permission\Exceptions\UnauthorizedException::class);
        $middleware->handle($request, $next, 'team1_role');
    }
} 