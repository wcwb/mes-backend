<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Jetstream;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TestAdminTeamUserPermission extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'test:admin-user-permission {--role=viewer : 要分配的角色名称}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '测试admin团队用户是否拥有view_orders权限';

    /**
     * 执行命令
     */
    public function handle()
    {
        $roleName = $this->option('role');
        $this->info("开始测试admin团队用户(角色:{$roleName})是否有view_orders权限...");
        
        // 创建测试用户
        $user = User::create([
            'name' => 'Admin Team Test User',
            'email' => 'admin_test_' . time() . '@example.com', // 使用时间戳确保邮箱唯一
            'password' => Hash::make('password'),
        ]);
        
        $this->info("已创建测试用户: {$user->name} ({$user->email})");
        
        // 获取admin团队(ID=1)
        $adminTeam = Jetstream::newTeamModel()->find(1);
        
        if ($adminTeam) {
            // 将用户添加到admin团队
            $user->teams()->attach($adminTeam);
            
            // 设置为当前团队
            $user->current_team_id = $adminTeam->id;
            $user->save();
            
            $this->info("用户已添加到团队: {$adminTeam->name} (ID: {$adminTeam->id})");
            
            // 设置权限系统团队ID
            app(PermissionRegistrar::class)->setPermissionsTeamId($adminTeam->id);
            
            // 分配角色给用户
            $role = Role::where('name', $roleName)
                ->where('team_id', $adminTeam->id)
                ->first();
                
            if ($role) {
                $user->assignRole($role);
                $this->info("已分配角色 '{$role->name}' 给用户");
            } else {
                $this->warn("未找到团队 {$adminTeam->id} 中的 '{$roleName}' 角色");
            }
        } else {
            $this->error('未找到admin团队(ID=1)，请先创建');
            return 1;
        }
        
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