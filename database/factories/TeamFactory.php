<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'user_id' => User::factory(),
            'personal_team' => false,
        ];
    }
} 