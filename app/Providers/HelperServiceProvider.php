<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class HelperServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 如果助手文件存在，则加载它
        $file = app_path('Helpers/PermissionHelper.php');
        if (file_exists($file)) {
            require_once($file);
        }
    }

    /**
     * 引导服务
     */
    public function boot(): void
    {
        //
    }
} 