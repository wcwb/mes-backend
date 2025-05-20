<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        
        // 运行所有种子
        $this->call([
            TeamsSeeder::class,           // 1. 先创建特殊团队
            SuperAdminSeeder::class,       // 3. 最后创建超级管理员并分配权限
            ModulePermissionSeeder::class, // 2. 然后创建模块权限
        ]);
    }
}
