<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TeamScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // 使用 request()->user() 而不是 Auth()->user() 的原因：
        // 1. 在全局作用域中，Auth facade 可能还未完全初始化
        // 2. request()->user() 更可靠，因为它直接从当前请求中获取用户
        // 3. 这种方式可以确保在队列任务或其他非HTTP请求场景下也能正常工作
        $user = request()->user();

        // 如果没有认证用户（如在种子数据或命令行中），不应用作用域
        if (!$user) {
            return;
        }

        if ($user->is_super_admin) {
            return;
        }

        $builder->where('team_id', $user->current_team_id);
    }
}
