<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * 处理User模型的"删除"事件
     * 
     * 当用户被软删除时，相关联的数据也应被处理
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function deleted(User $user)
    {
        // 确保这是一个软删除操作
        if (!$user->isForceDeleting()) {
            DB::beginTransaction();
            
            try {
                // 标记用户的团队成员关系为已删除
                DB::table('team_user')
                    ->where('user_id', $user->id)
                    ->update([
                        'deleted_at' => now()
                    ]);
                
                // 处理用户拥有的团队（如果业务需要，可以软删除或转移所有权）
                // 这里的策略取决于业务需求，例如：
                // 1. 软删除该用户拥有的所有团队
                // 2. 转移团队所有权给其他成员
                // 3. 保留团队但标记为特殊状态
                
                // 示例：软删除用户拥有的个人团队
                $user->ownedTeams()
                    ->where('personal_team', true)
                    ->each(function ($team) {
                        $team->delete();
                    });
                
                // 记录日志
                Log::channel('team_management')->info('用户级联软删除完成', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'operator_id' => auth()->id() ?? null
                ]);
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                
                Log::channel('api_errors')->error('用户级联软删除失败', [
                    'user_id' => $user->id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'operator_id' => auth()->id() ?? null
                ]);
            }
        }
    }
    
    /**
     * 处理User模型的"恢复"事件
     * 
     * 当用户被恢复时，相关联的数据也应被恢复
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function restored(User $user)
    {
        DB::beginTransaction();
        
        try {
            // 恢复用户的团队成员关系
            DB::table('team_user')
                ->where('user_id', $user->id)
                ->whereNotNull('deleted_at')
                ->update([
                    'deleted_at' => null
                ]);
            
            // 恢复用户拥有的个人团队
            $user->ownedTeams()
                ->withTrashed()
                ->where('personal_team', true)
                ->each(function ($team) {
                    $team->restore();
                });
            
            // 记录日志
            Log::channel('team_management')->info('用户及关联数据恢复完成', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'operator_id' => auth()->id() ?? null
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::channel('api_errors')->error('用户及关联数据恢复失败', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operator_id' => auth()->id() ?? null
            ]);
        }
    }
    
    /**
     * 处理User模型的"强制删除"事件
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function forceDeleted(User $user)
    {
        DB::beginTransaction();
        
        try {
            // 物理删除用户的团队成员关系
            DB::table('team_user')
                ->where('user_id', $user->id)
                ->delete();
                
            // 处理用户拥有的团队
            // 示例：强制删除用户拥有的个人团队
            $user->ownedTeams()
                ->withTrashed()
                ->where('personal_team', true)
                ->each(function ($team) {
                    $team->forceDelete();
                });
            
            // 记录日志
            Log::channel('security')->warning('用户及关联数据已永久删除', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'operator_id' => auth()->id() ?? null
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::channel('api_errors')->error('用户及关联数据永久删除失败', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operator_id' => auth()->id() ?? null
            ]);
        }
    }
} 