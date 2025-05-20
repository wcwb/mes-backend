<?php

namespace App\Http\Middleware;

use App\Helpers\TeamConstants;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetSpatieTeamId
{
    /**
     * 处理传入的请求
     * 如果用户已登录且有当前团队，则设置 Spatie Permission 的团队作用域
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 检查用户是否已登录
        if ($request->user()) {
            // 检查用户是否有当前团队
            if ($currentTeam = $request->user()->currentTeam) {
                // 设置 Spatie Permission 的团队 ID
                app(PermissionRegistrar::class)->setPermissionsTeamId($currentTeam->id);
            } else {
                // 如果用户没有当前团队，则使用默认团队ID
                app(PermissionRegistrar::class)->setPermissionsTeamId(TeamConstants::DEFAULT_TEAM_ID);
            }
        }

        return $next($request);
    }
} 