<?php

use App\Http\Middleware\ApiErrorHandler;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureUserHasTeam;
use App\Http\Middleware\PreventTeamLeaving;
use App\Http\Middleware\SetSpatieTeamId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API中间件组
        $middleware->api([
            HandleCors::class,
            EnsureFrontendRequestsAreStateful::class,
            SetSpatieTeamId::class,
            ApiErrorHandler::class,
            EnsureUserHasTeam::class,
            PreventTeamLeaving::class,
        ]);
        
        // Web中间件组
        $middleware->web([
            HandleCors::class,
            SetSpatieTeamId::class,
            EnsureUserHasTeam::class,
            PreventTeamLeaving::class,
        ]);
        
        // 添加全局中间件
        $middleware->append(SetSpatieTeamId::class);
        
        // 优先级高的中间件
        $middleware->prepend(ApiErrorHandler::class);
        
        // 注册路由中间件别名
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API异常处理
        $exceptions->renderable(function (\Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'message' => __('提供的数据无效'),
                        'errors' => $e->errors(),
                    ], $e->status);
                }
                
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'message' => __('未授权，请先登录'),
                    ], 401);
                }
                
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'message' => $e->getMessage() ?: __('没有权限执行此操作'),
                    ], 403);
                }
                
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json([
                        'message' => __('请求的资源不存在'),
                    ], 404);
                }
                
                // 生产环境不暴露详细错误
                $message = config('app.debug')
                    ? __('服务器错误: :error', ['error' => $e->getMessage()])
                    : __('处理请求时发生错误，请稍后重试');
                    
                return response()->json([
                    'message' => $message,
                ], 500);
            }
            
            return null;
        });
    })->create();
