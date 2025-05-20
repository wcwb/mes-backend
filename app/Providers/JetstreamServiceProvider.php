<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * 注册任何应用服务
     */
    public function register(): void
    {
        //
    }

    /**
     * 启动应用服务
     */
    public function boot(): void
    {
        Jetstream::useTeamModel(\App\Models\Team::class);

        // 注册团队相关操作
        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);

        // 设置TeamModel
        Jetstream::useTeamModel(\App\Models\Team::class);
    }
} 