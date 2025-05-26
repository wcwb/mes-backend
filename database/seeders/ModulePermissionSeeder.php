<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;

class ModulePermissionSeeder extends Seeder
{
    /**
     * 运行数据库填充
     */
    public function run(): void
    {
        // 清除缓存
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 模块列表
        $modules = [
            'orders',
            'customers',
            'suppliers',
            'invoices',
            'users',
            'roles',
            'tasks',
            'notifications',
            'logs',
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
            'import',
            'print',
            'approve',
            'reject',
            'cancel',
            'archive',
            'restore',
            'forceDelete',
            'emailTo'
        ];

        $teams = [3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
        $roleNames = ['owner', 'viewer', 'creator', 'approver', 'editor'];

        // 1. 批量创建所有权限
        $this->createPermissionsBatch($modules, $actions, $teams);

        // 2. 批量创建所有角色
        $this->createRolesBatch($roleNames, $teams);

        // 3. 批量分配权限给角色
        $this->assignPermissionsToRoles($modules, $actions, $teams, $roleNames);
    }

    /**
     * 批量创建权限
     */
    private function createPermissionsBatch(array $modules, array $actions, array $teams): void
    {
        $permissions = [];
        $timestamp = now();

        foreach ($teams as $team) {
            foreach ($modules as $module) {
                foreach ($actions as $action) {
                    $permissions[] = [
                        'name' => "{$action}_{$module}",
                        'guard_name' => 'web',
                        'team_id' => $team,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }
        }

        // 使用 upsert 避免重复插入
        DB::table('permissions')->upsert(
            $permissions,
            ['name', 'guard_name', 'team_id'], // 唯一键
            ['updated_at'] // 更新字段
        );
    }

    /**
     * 批量创建角色
     */
    private function createRolesBatch(array $roleNames, array $teams): void
    {
        $roles = [];
        $timestamp = now();

        foreach ($teams as $team) {
            foreach ($roleNames as $roleName) {
                $roles[] = [
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'team_id' => $team,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        // 使用 upsert 避免重复插入
        DB::table('roles')->upsert(
            $roles,
            ['name', 'guard_name', 'team_id'], // 唯一键
            ['updated_at'] // 更新字段
        );
    }

    /**
     * 批量分配权限给角色
     */
    private function assignPermissionsToRoles(array $modules, array $actions, array $teams, array $roleNames): void
    {
        // 获取所有权限和角色的映射
        $permissions = Permission::whereIn('team_id', $teams)
            ->get()
            ->keyBy(function ($permission) {
                return $permission->team_id . '_' . $permission->name;
            });

        $roles = Role::whereIn('team_id', $teams)
            ->get()
            ->keyBy(function ($role) {
                return $role->team_id . '_' . $role->name;
            });

        $rolePermissions = [];

        foreach ($teams as $team) {
            foreach ($roleNames as $roleName) {
                $roleKey = $team . '_' . $roleName;
                $role = $roles[$roleKey] ?? null;

                if (!$role) continue;

                foreach ($modules as $module) {
                    foreach ($actions as $action) {
                        $permissionKey = $team . '_' . $action . '_' . $module;
                        $permission = $permissions[$permissionKey] ?? null;

                        if (!$permission) continue;

                        // 根据角色和动作决定是否分配权限
                        if ($this->shouldAssignPermission($roleName, $action)) {
                            $rolePermissions[] = [
                                'permission_id' => $permission->id,
                                'role_id' => $role->id,
                            ];
                        }
                    }
                }
            }
        }

        // 批量插入角色权限关联
        if (!empty($rolePermissions)) {
            // 先清除可能存在的重复数据
            DB::table('role_has_permissions')->whereIn('role_id', $roles->pluck('id'))->delete();

            // 分批插入以避免内存问题
            $chunks = array_chunk($rolePermissions, 1000);
            foreach ($chunks as $chunk) {
                DB::table('role_has_permissions')->insert($chunk);
            }
        }
    }

    /**
     * 判断是否应该为角色分配特定权限
     */
    private function shouldAssignPermission(string $roleName, string $action): bool
    {
        $permissions = [
            'viewer' => ['view', 'print', 'emailTo'],
            'creator' => ['view', 'create', 'update', 'import', 'print', 'restore', 'emailTo'],
            'editor' => ['view', 'update', 'import', 'print', 'emailTo'],
            'approver' => ['approve', 'reject', 'cancel'],
            'owner' => [
                'view',
                'create',
                'update',
                'delete',
                'export',
                'import',
                'print',
                'archive',
                'restore',
                'forceDelete',
                'emailTo'
            ],
        ];

        return in_array($action, $permissions[$roleName] ?? []);
    }
}
