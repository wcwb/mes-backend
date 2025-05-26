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
        $teams = [
            [
                'name' => 'sewing',
                'personal_team' => false,
                'description' => 'sewing production team',
                'user_id' => 1,
            ],
            [
                'name' => 'office',
                'personal_team' => false,
                'description' => 'office team',
                'user_id' => 1,
            ],
            [
                'name' => 'cutting',
                'personal_team' => false,
                'description' => 'cutting production team',
                'user_id' => 1,
            ],
            [
                'name' => 'packing',
                'personal_team' => false,
                'description' => 'packing production team',
                'user_id' => 1,
            ],
            [
                'name' => 'warehouse',
                'personal_team' => false,
                'description' => 'warehouse team',
                'user_id' => 1,
            ],
            [
                'name' => 'quality',
                'personal_team' => false,
                'description' => 'quality control team',
                'user_id' => 1,
            ],
            [
                'name' => 'sales',
                'personal_team' => false,
                'description' => 'sales team',
                'user_id' => 1,
            ],
            [
                'name' => 'finance',
                'personal_team' => false,
                'description' => 'finance team',
                'user_id' => 1,
            ],
            [
                'name' => 'hr',
                'personal_team' => false,
                'description' => 'hr team',
                'user_id' => 1,
            ],
            [
                'name' => 'logistics',
                'personal_team' => false,
                'description' => 'logistics team',
                'user_id' => 1,
            ],
        ];


        // 创建admin团队
        $adminTeam = Jetstream::newTeamModel()->firstOrCreate(
            ['name' => 'admin'],
            [
                'user_id' => 1,
                'name' => 'admin',
                'personal_team' => false,
                'description' => 'administrator team for system',
            ]
        );

        // 创建default团队
        $defaultTeam = Jetstream::newTeamModel()->firstOrCreate(
            ['name' => 'default'],
            [
                'user_id' => 1,
                'name' => 'default',
                'personal_team' => false,
                'description' => 'default team for users are not belong to other team',
            ]
        );

        foreach ($teams as $team) {
            $team = Jetstream::newTeamModel()->create($team);
        }
    }
}
