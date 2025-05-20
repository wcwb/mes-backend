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
        
        // 设置超级管理员团队ID为1
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        
        // 创建超级管理员角色
        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'team_id' => 1
        ]);
        
        // 创建或获取超级管理员用户
        $superAdmin = User::firstOrCreate(
            ['email' => 'james@hyq.com'],
            [
                'name' => 'James',
                'password' => Hash::make('juWveg-kegnyq-3dewxu'),
                'email_verified_at' => now(),
            ]
        );
        
        // 获取admin团队
        $adminTeam = Jetstream::newTeamModel()->find(1); // admin团队(ID=1)
        
        // 确保超级管理员属于admin团队
        if (!$superAdmin->belongsToTeam($adminTeam)) {
            $superAdmin->teams()->attach($adminTeam);
        }
        
        // 将超级管理员的当前团队设置为admin团队
        $superAdmin->current_team_id = $adminTeam->id;
        $superAdmin->save();
        
        // 赋予超级管理员角色所有权限（包括团队1和团队2的权限）
        $allPermissions = Permission::where(function($query) {
            $query->where('team_id', 1)->orWhere('team_id', 2);
        })->get();
        
        $superAdminRole->syncPermissions($allPermissions);
        
        // 将超级管理员角色分配给用户
        $superAdmin->assignRole($superAdminRole);
        
        $this->command->info('超级管理员创建成功！');
        $this->command->info('用户名: James');
        $this->command->info('邮箱: james@hyq.com');
        $this->command->info('密码: juWveg-kegnyq-3dewxu');
    }
} 