<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Jetstream;

class CreateTeam implements CreatesTeams
{
    /**
     * 创建一个新的团队
     *
     * @param  array<string, mixed>  $input
     */
    public function create(User $user, array $input): Team
    {
        Gate::forUser($user)->authorize('create', Jetstream::newTeamModel());

        $team = Team::create([
            'name' => $input['name'],
            'user_id' => $user->id,
            'personal_team' => $input['name'] === $user->name,
        ]);

        $user->switchTeam($team);

        event(new TeamCreated($team));

        return $team;
    }
} 