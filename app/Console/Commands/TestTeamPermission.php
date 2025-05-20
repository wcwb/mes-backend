<?php

namespace App\Console\Commands;

use App\Helpers\PermissionHelper;
use App\Helpers\TeamConstants;
use App\Models\User;
use Illuminate\Console\Command;
use Laravel\Jetstream\Jetstream;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TestTeamPermission extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'test:team-permission {action=test} {user_id?}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '测试团队权限集成';

    /**
     * 执行命令
     */
    public function handle()
    {
        $action = $this->argument('action');
        $userId = $this->argument('user_id');

        switch ($action) {
            case 'test':
                $this->testTeamPermission();
                break;
            case 'check-user':
                $this->checkUserPermission($userId);
                break;
            case 'create-test-data':
                $this->createTestData();
                break;
            case 'team-switch-test':
                $this->testTeamSwitch($userId);
                break;
            default:
                $this->error('未知操作: ' . $action);
                break;
        }
    }

    /**
     * 执行团队权限测试
     */
    private function testTeamPermission()
    {
        $this->info('团队权限集成测试');
        $this->info('---------------------');
        
        // 检查权限系统配置
        $this->checkPermissionConfig();
        
        // 检查团队存在性
        $this->checkTeamsExist();
        
        // 检查关键中间件
        $this->checkMiddleware();
        
        $this->info("\n测试命令:");
        $this->info("- 检查用户权限: php artisan test:team-permission check-user {用户ID}");
        $this->info("- 创建测试数据: php artisan test:team-permission create-test-data");
        $this->info("- 测试团队切换: php artisan test:team-permission team-switch-test {用户ID}");
    }
    
    /**
     * 检查权限系统配置
     */
    private function checkPermissionConfig()
    {
        $this->info("\n检查权限系统配置:");
        
        // 检查team_foreign_key配置
        $teamForeignKey = config('permission.column_names.team_foreign_key');
        if ($teamForeignKey) {
            $this->line("✓ 团队外键配置: {$teamForeignKey}");
        } else {
            $this->error("✗ 团队外键未配置");
        }
        
        // 检查teams配置
        $teamsEnabled = config('permission.teams');
        if ($teamsEnabled) {
            $this->line("✓ 团队功能已启用");
        } else {
            $this->error("✗ 团队功能未启用");
        }
    }
    
    /**
     * 检查团队存在性
     */
    private function checkTeamsExist()
    {
        $this->info("\n检查团队存在性:");
        
        // 检查admin团队
        $adminTeam = Jetstream::newTeamModel()->find(TeamConstants::ADMIN_TEAM_ID);
        if ($adminTeam) {
            $this->line("✓ Admin团队存在 (ID: {$adminTeam->id}, 名称: {$adminTeam->name})");
        } else {
            $this->error("✗ Admin团队不存在");
        }
        
        // 检查default团队
        $defaultTeam = Jetstream::newTeamModel()->find(TeamConstants::DEFAULT_TEAM_ID);
        if ($defaultTeam) {
            $this->line("✓ Default团队存在 (ID: {$defaultTeam->id}, 名称: {$defaultTeam->name})");
        } else {
            $this->error("✗ Default团队不存在");
        }
    }
    
    /**
     * 检查关键中间件
     */
    private function checkMiddleware()
    {
        $this->info("\n检查关键中间件:");
        
        // 获取bootstrap/app.php内容
        if (file_exists(base_path('bootstrap/app.php'))) {
            $bootstrapContent = file_get_contents(base_path('bootstrap/app.php'));
            
            // 检查SetSpatieTeamId中间件
            if (strpos($bootstrapContent, 'SetSpatieTeamId') !== false) {
                $this->line("✓ SetSpatieTeamId中间件已注册");
            } else {
                $this->error("✗ SetSpatieTeamId中间件未注册");
            }
            
            // 检查CheckRole中间件
            if (strpos($bootstrapContent, 'CheckRole') !== false) {
                $this->line("✓ CheckRole中间件已注册");
            } else {
                $this->error("✗ CheckRole中间件未注册");
            }
            
            // 检查CheckPermission中间件
            if (strpos($bootstrapContent, 'CheckPermission') !== false) {
                $this->line("✓ CheckPermission中间件已注册");
            } else {
                $this->error("✗ CheckPermission中间件未注册");
            }
            
            // 检查EnsureUserHasTeam中间件
            if (strpos($bootstrapContent, 'EnsureUserHasTeam') !== false) {
                $this->line("✓ EnsureUserHasTeam中间件已注册");
            } else {
                $this->error("✗ EnsureUserHasTeam中间件未注册");
            }
        } else {
            $this->error("✗ 无法查找bootstrap/app.php文件");
        }
    }
    
    /**
     * 检查用户权限
     */
    private function checkUserPermission($userId)
    {
        if (!$userId) {
            $this->error('需要提供用户ID');
            return;
        }
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("未找到ID为 {$userId} 的用户");
            return;
        }
        
        $this->info("\n用户信息:");
        $this->line("用户ID: {$user->id}");
        $this->line("用户名: {$user->name}");
        $this->line("邮箱: {$user->email}");
        
        // 检查用户团队
        $this->info("\n用户团队:");
        $userTeams = $user->allTeams();
        if ($userTeams->isEmpty()) {
            $this->warn("用户不属于任何团队");
        } else {
            foreach ($userTeams as $team) {
                $isCurrent = $user->currentTeam && $user->currentTeam->id === $team->id;
                $this->line("- 团队ID: {$team->id}, 名称: {$team->name}" . ($isCurrent ? ' (当前团队)' : ''));
            }
        }
        
        // 如果用户有当前团队，检查在当前团队下的权限
        if ($user->currentTeam) {
            $teamId = $user->currentTeam->id;
            app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
            
            // 刷新用户关联
            $user->unsetRelation('roles')->unsetRelation('permissions');
            
            $this->info("\n当前团队权限 (团队ID: {$teamId}):");
            
            // 检查角色
            $this->line("\n用户角色:");
            $roles = $user->roles()->get();
            if ($roles->isEmpty()) {
                $this->warn("用户在当前团队没有任何角色");
            } else {
                foreach ($roles as $role) {
                    $this->line("- {$role->name}");
                }
            }
            
            // 检查直接权限
            $this->line("\n用户直接权限:");
            $directPermissions = $user->getDirectPermissions();
            if ($directPermissions->isEmpty()) {
                $this->warn("用户在当前团队没有任何直接权限");
            } else {
                foreach ($directPermissions as $permission) {
                    $this->line("- {$permission->name}");
                }
            }
            
            // 检查所有权限（包括通过角色获得的）
            $this->line("\n用户所有权限（包括通过角色获得的）:");
            $allPermissions = $user->getAllPermissions();
            if ($allPermissions->isEmpty()) {
                $this->warn("用户在当前团队没有任何权限");
            } else {
                foreach ($allPermissions as $permission) {
                    $this->line("- {$permission->name}");
                }
            }
        } else {
            $this->warn("\n用户没有当前团队，无法检查权限");
        }
    }
    
    /**
     * 创建测试数据
     */
    private function createTestData()
    {
        $this->info('创建测试数据...');
        
        // 创建测试用户
        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => '测试用户',
                'password' => bcrypt('password'),
            ]
        );
        
        // 检查是否已存在测试团队
        $testTeam = Jetstream::newTeamModel()->where('name', '测试团队')->first();
        
        // 如果没有找到测试团队，则创建一个新的
        if (!$testTeam) {
            $testTeam = Jetstream::newTeamModel();
            $testTeam->name = '测试团队';
            $testTeam->user_id = $testUser->id;
            $testTeam->personal_team = false;
            $testTeam->save();
        }
        
        // 添加用户到测试团队
        if (!$testUser->belongsToTeam($testTeam)) {
            $testUser->teams()->attach($testTeam);
        }
        
        // 设置为当前团队
        $testUser->current_team_id = $testTeam->id;
        $testUser->save();
        
        // 设置Spatie团队ID
        app(PermissionRegistrar::class)->setPermissionsTeamId($testTeam->id);
        
        // 创建测试角色
        $testRole = Role::firstOrCreate([
            'name' => 'tester',
            'guard_name' => 'web',
            'team_id' => $testTeam->id
        ]);
        
        // 创建测试权限
        $testPermission = Permission::firstOrCreate([
            'name' => 'test.permission',
            'guard_name' => 'web',
            'team_id' => $testTeam->id
        ]);
        
        // 分配权限给角色
        if (!$testRole->hasPermissionTo($testPermission)) {
            $testRole->givePermissionTo($testPermission);
        }
        
        // 分配角色给用户
        if (!$testUser->hasRole($testRole)) {
            $testUser->assignRole($testRole);
        }
        
        $this->info("测试数据创建成功:");
        $this->line("- 测试用户ID: {$testUser->id}, 名称: {$testUser->name}, 邮箱: {$testUser->email}");
        $this->line("- 测试团队ID: {$testTeam->id}, 名称: {$testTeam->name}");
        $this->line("- 测试角色: tester");
        $this->line("- 测试权限: test.permission");
        
        $this->info("\n您可以使用以下命令检查测试用户的权限:");
        $this->line("php artisan test:team-permission check-user {$testUser->id}");
    }
    
    /**
     * 测试团队切换
     */
    private function testTeamSwitch($userId)
    {
        if (!$userId) {
            $this->error('需要提供用户ID');
            return;
        }
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("未找到ID为 {$userId} 的用户");
            return;
        }
        
        $this->info('测试团队切换...');
        
        // 获取用户所有团队
        $teams = $user->allTeams();
        
        if ($teams->count() < 2) {
            $this->error("用户至少需要属于两个团队才能测试团队切换");
            return;
        }
        
        $currentTeam = $user->currentTeam;
        $this->info("当前团队: ID={$currentTeam->id}, 名称={$currentTeam->name}");
        
        // 检查当前团队权限
        app(PermissionRegistrar::class)->setPermissionsTeamId($currentTeam->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        $this->info("\n当前团队角色:");
        $roles = $user->roles()->get();
        foreach ($roles as $role) {
            $this->line("- {$role->name}");
        }
        
        // 选择另一个团队
        $otherTeam = $teams->first(function ($team) use ($currentTeam) {
            return $team->id !== $currentTeam->id;
        });
        
        $this->info("\n切换到团队: ID={$otherTeam->id}, 名称={$otherTeam->name}");
        
        // 切换团队
        $user->switchTeam($otherTeam);
        $user->refresh();
        
        // 手动更新权限团队ID (在实际应用中由TeamSwitched事件处理)
        app(PermissionRegistrar::class)->setPermissionsTeamId($otherTeam->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        $this->info("\n新团队角色:");
        $newRoles = $user->roles()->get();
        foreach ($newRoles as $role) {
            $this->line("- {$role->name}");
        }
        
        $this->info("\n团队切换测试完成");
    }
} 