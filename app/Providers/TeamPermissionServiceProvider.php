<?php

namespace App\Providers;

use App\Helpers\PermissionHelper;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Events\TeamSwitched;

class TeamPermissionServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        //
    }

    /**
     * 引导服务
     */
    public function boot(): void
    {
        // 监听团队切换事件，确保权限上下文更新
        Event::listen(TeamSwitched::class, function ($event) {
            // 当用户切换到新团队时，更新权限系统团队ID
            PermissionHelper::setCurrentTeamId($event->team->id);
            
            // 刷新用户权限缓存
            PermissionHelper::refreshUserPermissionCache($event->user);
        });
    }
} 