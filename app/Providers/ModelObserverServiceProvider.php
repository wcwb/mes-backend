<?php

namespace App\Providers;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Observers\TeamInvitationObserver;
use App\Observers\TeamObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class ModelObserverServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * 引导服务
     *
     * @return void
     */
    public function boot(): void
    {
        // 注册模型观察器，实现级联软删除功能
        Team::observe(TeamObserver::class);
        User::observe(UserObserver::class);
        TeamInvitation::observe(TeamInvitationObserver::class);
    }
} 