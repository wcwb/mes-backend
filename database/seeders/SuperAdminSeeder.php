<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Jetstream;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    /**
     * 运行数据库填充
     */
    public function run(): void
    {
        // 清除缓存
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        
        // 设置权限团队ID为1（admin团队）
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        
        // 创建或获取超级管理员用户(ID=1)
        $superAdmin = User::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'James',
                'surname' => 'Wang',
                'work_no' => 'admin01',
                'phone' => '0832609600',
                'position' => 1,
                'status' => 'active',
                'is_super_admin' => true,
                'abbreviation' => 'admin',
                'email' => 'james@hyq.com',
                'password' => Hash::make('juWveg-kegnyq-3dewxu'),
                'email_verified_at' => now(),
            ]
        );
        
        // 获取admin团队(ID=1)
        $adminTeam = Jetstream::newTeamModel()->find(1);
        
        // 如果存在admin团队，确保超级管理员属于该团队
        if ($adminTeam && !$superAdmin->belongsToTeam($adminTeam)) {
            $superAdmin->teams()->attach($adminTeam);
        
            // 设置admin团队为当前团队
        $superAdmin->current_team_id = $adminTeam->id;
        $superAdmin->save();
        }
        
        // 创建或获取owner角色
        $ownerRole = Role::firstOrCreate(
            [
                'name' => 'owner',
                'guard_name' => 'web',
                'team_id' => 1
            ]
        );
        
        // 获取所有权限并分配给owner角色
        $allPermissions = Permission::all();
        $ownerRole->syncPermissions($allPermissions);
        
        // 将owner角色分配给超级管理员
        $superAdmin->assignRole($ownerRole);
        
        // 为确保超级管理员拥有所有权限，也直接分配所有权限
        $superAdmin->syncPermissions($allPermissions);
        
        $this->command->info('超级管理员创建/更新成功！');
        $this->command->info('用户名: James');
        $this->command->info('邮箱: james@hyq.com');
        $this->command->info('密码: juWveg-kegnyq-3dewxu');
    }
} 