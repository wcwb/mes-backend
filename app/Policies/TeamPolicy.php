<?php

namespace App\Policies;

use App\Helpers\PermissionHelper;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * 确定用户是否可以删除团队
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Team  $team
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Team $team)
    {
        // 超级管理员可以删除任何团队
        if (PermissionHelper::isSuperAdmin()) {
            return true;
        }
        
        // 拥有团队删除权限的用户可以删除
        if ($user->hasPermissionTo('teams.delete')) {
            return true;
        }
        
        return false;
    }

    /**
     * 确定用户是否可以强制删除团队
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Team  $team
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Team $team)
    {
        // 只有超级管理员可以强制删除团队
        return PermissionHelper::isSuperAdmin();
    }
} 