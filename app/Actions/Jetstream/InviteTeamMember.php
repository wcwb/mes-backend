<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Events\InvitingTeamMember;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Jetstream\Rules\Role;

class InviteTeamMember implements InvitesTeamMembers
{
    /**
     * 邀请团队成员加入给定的团队
     *
     * @param  array<string, mixed>  $input
     */
    public function invite(User $user, Team $team, string $email, string $role = null): void
    {
        // 确保当前用户是团队拥有者
        if ($team->user_id !== $user->id) {
            Log::warning('非团队拥有者尝试邀请成员', [
                'user_id' => $user->id,
                'team_id' => $team->id,
                'email' => $email
            ]);
            
            throw ValidationException::withMessages([
                'email' => __('只有团队拥有者可以邀请成员.'),
            ]);
        }

        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($team, $email, $role);
        
        DB::beginTransaction();
        
        try {
            // 检查是否在尝试邀请已注册用户
            $invitedUser = User::where('email', $email)->first();
            
            if ($invitedUser) {
                // 检查用户是否已经是团队成员
                if ($invitedUser->belongsToTeam($team)) {
                    throw ValidationException::withMessages([
                        'email' => __('该用户已是团队成员.'),
                    ]);
                }
                
                // 获取邀请者的所有团队
                $userTeams = $user->allTeams();
                
                // 获取default团队(ID=2)
                $defaultTeam = Jetstream::newTeamModel()->find(2);
                
                // 如果邀请者只有一个团队，则只能从default团队邀请用户
                if (count($userTeams) === 1 && $defaultTeam) {
                    $userExists = User::where('email', $email)
                        ->whereHas('teams', function ($query) use ($defaultTeam) {
                            $query->where('team_id', $defaultTeam->id);
                        })
                        ->exists();
                        
                    if (!$userExists) {
                        throw ValidationException::withMessages([
                            'email' => __('您只能从default团队邀请用户.'),
                        ]);
                    }
                }
            }
    
            InvitingTeamMember::dispatch($team, $email, $role);
    
            $invitation = $team->teamInvitations()->create([
                'email' => $email,
                'role' => $role,
                'expires_at' => now()->addDays(7), // 设置邀请过期时间为7天
            ]);
            
            // 记录邀请日志
            Log::info('团队邀请已发送', [
                'team_id' => $team->id,
                'invited_by' => $user->id,
                'invited_email' => $email,
                'role' => $role,
                'invitation_id' => $invitation->id
            ]);
            
            Mail::to($email)->send(new TeamInvitation($invitation));
            
            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('发送团队邀请时出错', [
                'team_id' => $team->id,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            throw ValidationException::withMessages([
                'email' => __('发送邀请时发生错误，请稍后重试.'),
            ]);
        }
    }

    /**
     * 验证团队成员邀请
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validate(Team $team, string $email, ?string $role): void
    {
        $rules = array_filter([
            'email' => ['required', 'email', Rule::unique('team_invitations')->where(fn (Builder $query) => $query->where('team_id', $team->id))],
            'role' => Jetstream::hasRoles() ? ['required', 'string', new Role] : null,
        ]);

        $validator = Validator::make(['email' => $email, 'role' => $role], $rules);

        if (Jetstream::hasRoles() && $validator->fails()) {
            throw ValidationException::withMessages([
                'role' => __('给定的角色无效.'),
            ]);
        }

        $validator->validate();
    }
} 