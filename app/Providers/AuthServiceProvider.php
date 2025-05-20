<?php

namespace App\Providers;

use App\Models\Team;
use App\Policies\TeamPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * 需要注册的策略映射
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Team::class => TeamPolicy::class,
    ];

    /**
     * 注册任何认证/授权服务
     */
    public function boot(): void
    {
        // 注册策略
        $this->registerPolicies();

        // 超级管理员拥有所有权限
        Gate::after(function ($user, $ability) {
            return $user->hasRole('super_admin');
        });
    }
} 