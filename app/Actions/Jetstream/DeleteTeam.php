<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Laravel\Jetstream\Contracts\DeletesTeams;

class DeleteTeam implements DeletesTeams
{
    /**
     * 删除给定的团队
     * 
     * 该方法通过软删除实现团队删除
     * TeamObserver将处理成员关系和权限清理
     *
     * @param  \App\Models\Team  $team
     * @return void
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function delete(Team $team): void
    {
        // 检查删除权限
        Gate::authorize('delete', $team);
        
        // 使用purge方法，会触发软删除及相关观察者事件
        $team->purge();
    }
    
    /**
     * 强制删除给定的团队（绕过软删除）
     * 
     * @param  \App\Models\Team  $team
     * @return void
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function forceDelete(Team $team): void
    {
        // 检查强制删除权限
        Gate::authorize('forceDelete', $team);
        
        // 执行强制删除，会触发forceDelete观察者事件
        $team->forceRemove();
    }
} 