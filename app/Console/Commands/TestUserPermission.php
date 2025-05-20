<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Jetstream;
use Spatie\Permission\PermissionRegistrar;

class TestUserPermission extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'test:user-permission';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '测试新用户是否拥有view_orders权限';

    /**
     * 执行命令
     */
    public function handle()
    {
        $this->info('开始测试新用户是否有view_orders权限...');
        
        // 创建测试用户
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test_' . time() . '@example.com', // 使用时间戳确保邮箱唯一
            'password' => Hash::make('password'),
        ]);
        
        $this->info("已创建测试用户: {$user->name} ({$user->email})");
        
        // 获取default团队
        $defaultTeam = Jetstream::newTeamModel()->find(1);
        
        if ($defaultTeam) {
            // 将用户添加到default团队
            $user->teams()->attach($defaultTeam);
            
            // 设置为当前团队
            $user->current_team_id = $defaultTeam->id;
            $user->save();
            
            $this->info("用户已添加到团队: {$defaultTeam->name} (ID: {$defaultTeam->id})");
        } else {
            $this->error('未找到默认团队(ID=2)，请先创建');
            return 1;
        }
        
        // 设置权限系统团队ID
        app(PermissionRegistrar::class)->setPermissionsTeamId($defaultTeam->id);
        
        // 刷新用户关联
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        // 检查用户是否有view_orders权限
        $hasPermission = $user->can('view_orders');
        
        if ($hasPermission) {
            $this->info('测试结果: 用户拥有view_orders权限 ✓');
        } else {
            $this->warn('测试结果: 用户没有view_orders权限 ✗');
        }
        
        // 检查用户所有权限
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        
        if (count($permissions) > 0) {
            $this->info('用户拥有的所有权限:');
            foreach ($permissions as $permission) {
                $this->line(" - {$permission}");
            }
        } else {
            $this->info('用户没有任何权限');
        }
        
        // 检查用户角色
        $roles = $user->roles->pluck('name')->toArray();
        
        if (count($roles) > 0) {
            $this->info('用户拥有的所有角色:');
            foreach ($roles as $role) {
                $this->line(" - {$role}");
            }
        } else {
            $this->info('用户没有任何角色');
        }
        
        // 清理测试数据
        if ($this->confirm('是否删除测试用户?', true)) {
            $user->teams()->detach();
            $user->delete();
            $this->info('测试用户已删除');
        }
        
        return 0;
    }
} 