<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Laravel\Jetstream\Jetstream;

class TeamsSeeder extends Seeder
{
    /**
     * 运行数据库填充
     */
    public function run(): void
    {
        // 获取一个管理员用户（如果不存在则创建一个）
        $adminUser = User::firstOrCreate(
            ['email' => 'james@hyq.com'],
            [
                'name' => 'james',
                'password' => bcrypt('juWveg-kegnyq-3dewxu'),
                'email_verified_at' => now(),
            ]
        );
        
        // 创建admin团队（ID=1）
        $adminTeam = Jetstream::newTeamModel()->firstOrCreate(
            ['id' => 1],
            [
                'user_id' => $adminUser->id,
                'name' => 'admin',
                'personal_team' => false,
            ]
        );
        
        // 创建default团队（ID=2）
        $defaultTeam = Jetstream::newTeamModel()->firstOrCreate(
            ['id' => 2],
            [
                'user_id' => $adminUser->id,
                'name' => 'default',
                'personal_team' => false,
            ]
        );
        
        // 确保用户属于这些团队
        if (!$adminUser->belongsToTeam($adminTeam)) {
            $adminUser->teams()->attach($adminTeam);
        }
        
        if (!$adminUser->belongsToTeam($defaultTeam)) {
            $adminUser->teams()->attach($defaultTeam);
        }
        
        // 设置admin团队为当前团队
        if (!$adminUser->current_team_id) {
            $adminUser->current_team_id = $adminTeam->id;
            $adminUser->save();
        }
        
        $this->command->info('特殊团队创建成功！');
        $this->command->info('团队 1: admin');
        $this->command->info('团队 2: default');
    }
} 