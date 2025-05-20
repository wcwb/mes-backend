<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class PermissionHelper
{
    /**
     * 检查当前用户是否为超级管理员
     * 
     * @return bool
     */
    public static function isSuperAdmin(): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }
        
        // 检查用户是否有super_admin角色
        return $user->hasRole(TeamConstants::SUPER_ADMIN_ROLE);
    }
    
    /**
     * 设置当前请求的权限团队ID
     *
     * @param int|null $teamId 如果为null，则使用默认团队ID
     * @return void
     */
    public static function setCurrentTeamId(?int $teamId = null): void
    {
        $teamId = $teamId ?? TeamConstants::DEFAULT_TEAM_ID;
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($teamId);
    }
    
    /**
     * 获取当前的权限团队ID
     *
     * @return int|null
     */
    public static function getCurrentTeamId(): ?int
    {
        return app(\Spatie\Permission\PermissionRegistrar::class)->getPermissionsTeamId();
    }
    
    /**
     * 刷新用户权限和角色关系
     *
     * @param \App\Models\User $user
     * @return void
     */
    public static function refreshUserPermissionCache($user): void
    {
        if ($user) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
} 