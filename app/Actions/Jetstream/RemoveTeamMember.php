<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\RemovesTeamMembers;
use Laravel\Jetstream\Events\RemovingTeamMember;
use Laravel\Jetstream\Jetstream;

class RemoveTeamMember implements RemovesTeamMembers
{
    /**
     * 从给定的团队中移除团队成员
     */
    public function remove(User $user, Team $team, User $teamMember): void
    {
        // 记录操作日志
        Log::info('尝试移除团队成员', [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'team_member_id' => $teamMember->id
        ]);
        
        // 确保当前用户是团队拥有者
        if ($team->user_id !== $user->id) {
            Log::warning('非团队拥有者尝试移除成员', [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'team_member_id' => $teamMember->id
            ]);
            
            throw new AuthorizationException('只有团队拥有者可以移除成员.');
        }
        
        // 防止团队拥有者移除自己
        if ($teamMember->id === $team->user_id) {
            throw ValidationException::withMessages([
                'team' => ['团队拥有者不能被移除.'],
            ]);
        }
        
        // 普通成员不能自己离开团队
        if ($user->id === $teamMember->id && $user->id !== $team->user_id) {
            throw ValidationException::withMessages([
                'team' => ['您不能离开团队，请联系团队拥有者.'],
            ]);
        }

        DB::beginTransaction();
        
        try {
            // 确保用户在移除后仍有团队
            $this->ensureUserHasTeamAfterRemoval($teamMember, $team);
    
            Gate::forUser($user)->authorize('removeTeamMember', $team);
    
            RemovingTeamMember::dispatch($team, $teamMember);
    
            $team->removeUser($teamMember);
            
            // 记录成功移除成员的日志
            Log::info('成功移除团队成员', [
                'team_id' => $team->id,
                'removed_by' => $user->id,
                'removed_user' => $teamMember->id
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('移除团队成员时出错', [
                'team_id' => $team->id,
                'user_id' => $teamMember->id,
                'error' => $e->getMessage()
            ]);
            
            throw ValidationException::withMessages([
                'team' => ['移除成员时发生错误: ' . $e->getMessage()],
            ]);
        }
    }
    
    /**
     * 确保用户在被移除后仍然属于至少一个团队
     */
    protected function ensureUserHasTeamAfterRemoval(User $teamMember, Team $team): void
    {
        // 获取用户所有团队
        $userTeams = $teamMember->allTeams();
        
        // 如果用户只属于即将被移除的团队，则将其添加到default团队
        if (count($userTeams) <= 1 && $userTeams->contains('id', $team->id)) {
            $defaultTeam = Jetstream::newTeamModel()->find(2);
            
            if ($defaultTeam) {
                $teamMember->teams()->attach($defaultTeam);
                
                // 如果当前团队是用户的当前团队，切换到default团队
                if ($teamMember->current_team_id === $team->id) {
                    $teamMember->forceFill(['current_team_id' => $defaultTeam->id])->save();
                }
                
                Log::info('用户被添加到default团队', [
                    'user_id' => $teamMember->id,
                    'default_team_id' => $defaultTeam->id
                ]);
            } else {
                Log::error('未找到default团队，无法将用户添加到新团队', [
                    'user_id' => $teamMember->id
                ]);
                
                throw new \Exception('未找到default团队，无法完成移除操作');
            }
        }
    }
} 