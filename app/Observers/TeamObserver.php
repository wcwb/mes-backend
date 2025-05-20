<?php

namespace App\Observers;

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeamObserver
{
    /**
     * 处理Team模型的"删除"事件
     * 
     * 当团队被软删除时，相关联的数据也应被软删除
     *
     * @param  \App\Models\Team  $team
     * @return void
     */
    public function deleted(Team $team)
    {
        // 确保这是一个软删除操作
        if (!$team->isForceDeleting()) {
            DB::beginTransaction();
            
            try {
                // 软删除所有团队邀请
                $team->teamInvitations()->update([
                    'deleted_at' => now()
                ]);
                
                // 标记团队成员关系为已删除
                // 注意：这里使用raw SQL因为pivot模型不总是完全支持软删除
                DB::table('team_user')
                    ->where('team_id', $team->id)
                    ->update([
                        'deleted_at' => now()
                    ]);
                
                // 记录日志
                Log::channel('team_management')->info('团队级联软删除完成', [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'user_id' => auth()->id() ?? null
                ]);
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                
                Log::channel('api_errors')->error('团队级联软删除失败', [
                    'team_id' => $team->id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => auth()->id() ?? null
                ]);
            }
        }
    }
    
    /**
     * 处理Team模型的"恢复"事件
     * 
     * 当团队被恢复时，相关联的数据也应被恢复
     *
     * @param  \App\Models\Team  $team
     * @return void
     */
    public function restored(Team $team)
    {
        DB::beginTransaction();
        
        try {
            // 恢复所有团队邀请
            $team->teamInvitations()->withTrashed()->restore();
            
            // 恢复团队成员关系
            DB::table('team_user')
                ->where('team_id', $team->id)
                ->whereNotNull('deleted_at')
                ->update([
                    'deleted_at' => null
                ]);
            
            // 记录日志
            Log::channel('team_management')->info('团队及关联数据恢复完成', [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'user_id' => auth()->id() ?? null
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::channel('api_errors')->error('团队及关联数据恢复失败', [
                'team_id' => $team->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id() ?? null
            ]);
        }
    }
    
    /**
     * 处理Team模型的"强制删除"事件
     *
     * @param  \App\Models\Team  $team
     * @return void
     */
    public function forceDeleted(Team $team)
    {
        DB::beginTransaction();
        
        try {
            // 强制删除所有团队邀请
            $team->teamInvitations()->withTrashed()->forceDelete();
            
            // 物理删除团队成员关系
            DB::table('team_user')
                ->where('team_id', $team->id)
                ->delete();
            
            // 记录日志
            Log::channel('security')->warning('团队及关联数据已永久删除', [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'user_id' => auth()->id() ?? null
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::channel('api_errors')->error('团队及关联数据永久删除失败', [
                'team_id' => $team->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id() ?? null
            ]);
        }
    }
} 