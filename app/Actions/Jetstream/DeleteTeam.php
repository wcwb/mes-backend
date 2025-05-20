<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use Laravel\Jetstream\Contracts\DeletesTeams;

class DeleteTeam implements DeletesTeams
{
    /**
     * 删除给定的团队
     */
    public function delete(Team $team): void
    {
        $team->purge();
    }
} 