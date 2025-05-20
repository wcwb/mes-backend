<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * 应用程序的事件监听器映射
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * 注册任何事件
     */
    public function boot(): void
    {
        //
    }

    /**
     * 确定是否应该自动发现事件和监听器
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
} 