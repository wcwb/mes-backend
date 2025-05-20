<?php

namespace App\Http\Middleware;

use App\Helpers\PermissionHelper;
use App\Helpers\TeamConstants;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\PermissionRegistrar;

class CheckPermission
{
    /**
     * 处理传入的请求
     * 检查用户是否有指定的权限
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // 确保用户已登录
        if ($request->user()) {
            // 如果用户是超级管理员，直接通过
            if (PermissionHelper::isSuperAdmin()) {
                return $next($request);
            }
            
            // 设置团队ID
            if ($request->user()->currentTeam) {
                $teamId = $request->user()->currentTeam->id;
            } else {
                // 使用默认团队ID
                $teamId = TeamConstants::DEFAULT_TEAM_ID;
            }
            
            app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
            
            // 刷新用户关联
            $request->user()->unsetRelation('roles')->unsetRelation('permissions');
            
            // 检查用户是否有指定权限
            if (!$request->user()->hasPermissionTo($permission)) {
                throw UnauthorizedException::forPermissions([$permission]);
            }
        } else {
            throw UnauthorizedException::notLoggedIn();
        }
        
        return $next($request);
    }
} 