<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 运行所有种子
        $this->call([
            TeamsSeeder::class,           // 1. 先创建特殊团队
            ModulePermissionSeeder::class, // 2. 然后创建模块权限
        ]);

        $superAdmin = User::create([
            'name' => 'James',
            'surname' => 'Wang',
            'work_no' => 'admin01',
            'phone' => '0832609600',
            'position' => 1,
            'status' => 'active',
            'is_super_admin' => true,
            'abbreviation' => 'admin',
            'email' => 'james@hyq.com',
            'current_team_id' => 1,
            'password' => bcrypt('juWveg-kegnyq-3dewxu'),
            'email_verified_at' => now(),
        ]);

        DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT MAX(id) FROM users))");

        $roles = ['creator', 'editor', 'owner', 'viewer', 'approver'];
        $teams = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

        // app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(1);
        User::factory(20)->create()->each(function ($user) use ($roles, $teams) {
            $role = $roles[array_rand($roles)];
            $team = $teams[array_rand($teams)];
            $user->teams()->attach($team, ['role' => $role]);
            $user->current_team_id = $team;
            $user->save(); // 保存更改
            // 设置权限系统团队上下文为 admin 团队（ID=1），因为角色只在这个团队中存在
            // $user->assignRole($role);
        });
    }
}
