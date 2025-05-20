<?php

namespace App\Observers;

use App\Helpers\TeamConstants;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeamObserver
{
    /**
     * 处理Team模型的"删除中"事件
     * 在团队被软删除前执行
     *
     * @param  \App\Models\Team  $team
     * @return void
     */
    public function deleting(Team $team)
    {
        // 防止删除特殊团队
        if (TeamConstants::isSpecialTeam($team->id)) {
            Log::channel('security')->warning('尝试删除特殊团队被阻止', [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'user_id' => auth()->id() ?? null
            ]);
            
            // 阻止特殊团队删除
            return false;
        }
    }

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
                // 获取default团队ID（使用缓存减少查询）
                $defaultTeamId = Cache::remember('default_team_id', 86400, function () {
                    return TeamConstants::DEFAULT_TEAM_ID;
                });
                
                // 处理用户的current_team_id
                // 查找当前团队ID为被删除团队的用户
                $affectedUsers = User::where('current_team_id', $team->id)->get();
                
                foreach ($affectedUsers as $user) {
                    // 如果用户只属于这个团队，将其current_team_id更改为默认团队
                    $otherTeamsCount = $user->teams()
                        ->where('teams.id', '!=', $team->id)
                        ->count();
                        
                    if ($otherTeamsCount === 0) {
                        $user->current_team_id = $defaultTeamId;
                        $user->save();
                    }
                }
                
                // 软删除所有团队邀请
                $team->teamInvitations()->update([
                    'deleted_at' => now()
                ]);
                
                // 标记团队成员关系为已删除
                DB::table('team_user')
                    ->where('team_id', $team->id)
                    ->update([
                        'deleted_at' => now()
                    ]);
                
                // 删除该团队绑定的所有角色-用户关系和权限-用户关系
                DB::table('model_has_roles')
                    ->where('team_id', $team->id)
                    ->delete();
                    
                DB::table('model_has_permissions')
                    ->where('team_id', $team->id)
                    ->delete();
                
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
            // 获取default团队ID（使用缓存减少查询）
            $defaultTeamId = Cache::remember('default_team_id', 86400, function () {
                return TeamConstants::DEFAULT_TEAM_ID;
            });
            
            // 处理用户的current_team_id
            User::where('current_team_id', $team->id)
                ->update(['current_team_id' => $defaultTeamId]);
            
            // 强制删除所有团队邀请
            $team->teamInvitations()->withTrashed()->forceDelete();
            
            // 物理删除团队成员关系
            DB::table('team_user')
                ->where('team_id', $team->id)
                ->delete();
            
            // 删除团队相关的所有权限和角色关系
            DB::table('model_has_roles')
                ->where('team_id', $team->id)
                ->delete();
                
            DB::table('model_has_permissions')
                ->where('team_id', $team->id)
                ->delete();
                
            // 删除团队日志和其他关联数据
            // 这里可以添加其他需要删除的关联数据
            
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