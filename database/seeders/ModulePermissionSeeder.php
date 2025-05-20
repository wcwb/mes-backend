<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ModulePermissionSeeder extends Seeder
{
    /**
     * 运行数据库填充
     */
    public function run(): void
    {
        // 清除缓存
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        
        // 设置admin团队ID为1
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        
        // 模块列表（注意warehouse只出现一次，避免重复）
        $modules = [
            'orders',
            'styles',
            'equipments',
            'warehouse',
            'cutting',
            'sewing',
            'packing',
            'quality',
            'materials',
            'hr',
            'office',
            'purchasing'
        ];
        
        // 权限动作列表
        $actions = [
            'view',
            'create',
            'update',
            'delete',
            'export',
            'print'
        ];
        
        // 创建admin团队(ID=1)的角色
        $roles = [
            'viewer' => Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web', 'team_id' => 1]),
            'creator' => Role::firstOrCreate(['name' => 'creator', 'guard_name' => 'web', 'team_id' => 1]),
            'editor' => Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web', 'team_id' => 1]),
            'owner' => Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web', 'team_id' => 1])
        ];
        
        // 记录所有创建的权限
        $createdPermissions = [];
        
        // 为每个模块创建权限
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $permissionName = "{$action}_{$module}";
                
                // 创建权限（如果不存在）
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'team_id' => 1
                ]);
                
                // 记录创建的权限
                $createdPermissions[$permissionName] = $permission;
                
                // 根据角色分配权限
                switch ($action) {
                    case 'view':
                    case 'export':
                    case 'print':
                        // viewer、creator、editor、owner 都有这些权限
                        $roles['viewer']->givePermissionTo($permission);
                        $roles['creator']->givePermissionTo($permission);
                        $roles['editor']->givePermissionTo($permission);
                        $roles['owner']->givePermissionTo($permission);
                        break;
                        
                    case 'create':
                        // creator、editor、owner 有创建权限
                        $roles['creator']->givePermissionTo($permission);
                        $roles['editor']->givePermissionTo($permission);
                        $roles['owner']->givePermissionTo($permission);
                        break;
                        
                    case 'update':
                        // editor、owner 有更新权限
                        $roles['editor']->givePermissionTo($permission);
                        $roles['owner']->givePermissionTo($permission);
                        break;
                        
                    case 'delete':
                        // 只有 owner 有删除权限
                        $roles['owner']->givePermissionTo($permission);
                        break;
                }
            }
        }
        
        $this->command->info('模块权限创建成功，共创建 ' . count($createdPermissions) . ' 个权限并分配给角色');
    }
} 