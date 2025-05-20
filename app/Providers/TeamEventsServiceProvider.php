<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\InvitingTeamMember;
use Laravel\Jetstream\Events\RemovingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Events\TeamMemberRemoved;
use Laravel\Jetstream\Jetstream;

class TeamEventsServiceProvider extends ServiceProvider
{
    // 缓存default团队实例
    protected $defaultTeam = null;
    
    /**
     * 注册服务
     */
    public function register(): void
    {
        //
    }

    /**
     * 引导服务
     */
    public function boot(): void
    {
        // 监听用户注册事件，确保用户至少属于一个团队
        Event::listen(Registered::class, function ($event) {
            $this->ensureUserHasTeam($event->user);
        });

        // 监听添加团队成员前事件
        Event::listen(AddingTeamMember::class, function ($event) {
            // 确保只有团队拥有者可以添加成员
            if ($event->team->user_id !== auth()->id()) {
                return false;
            }

            // 获取要添加的用户
            $user = User::findOrFail($event->email);
            
            // 检查用户是否已经是该团队的成员
            if ($user->belongsToTeam($event->team)) {
                return false;
            }

            // 使用事务确保操作原子性
            DB::beginTransaction();
            try {
                // 获取default团队(ID=2)
                $defaultTeam = $this->getDefaultTeam();
                
                // 如果是加入非default团队，并且用户已在default团队，则需要把用户从default团队移除
                if ($event->team->id != 2 && $defaultTeam && $user->belongsToTeam($defaultTeam)) {
                    $user->teams()->detach($defaultTeam);
                }
                
                // 如果是加入default团队，则需要把用户从其他所有团队移除
                if ($event->team->id == 2) {
                    foreach ($user->allTeams() as $team) {
                        if ($team->id != 2) {
                            $user->teams()->detach($team);
                        }
                    }
                }
                
                DB::commit();
                return true;
            } catch (\Exception $e) {
                DB::rollBack();
                report($e);
                return false;
            }
        });

        // 监听邀请团队成员前事件
        Event::listen(InvitingTeamMember::class, function ($event) {
            // 确保只有团队拥有者可以邀请成员
            return $event->team->user_id === auth()->id();
        });

        // 监听移除团队成员前事件
        Event::listen(RemovingTeamMember::class, function ($event) {
            // 确保只有团队拥有者可以移除成员
            if ($event->team->user_id !== auth()->id()) {
                return false;
            }
            
            // 如果要移除的不是所有者自己，则允许移除
            if ($event->teamMember->id !== $event->team->user_id) {
                // 获取被移除用户
                $user = User::findOrFail($event->teamMember->id);
                
                // 使用事务确保操作原子性
                DB::beginTransaction();
                try {
                    // 如果用户不属于任何其他团队，就将其添加到default团队(ID=2)
                    if (count($user->allTeams()) <= 1) {
                        $defaultTeam = $this->getDefaultTeam();
                        if ($defaultTeam) {
                            $user->teams()->attach($defaultTeam);
                            $user->switchTeam($defaultTeam);
                        }
                    }
                    
                    DB::commit();
                    return true;
                } catch (\Exception $e) {
                    DB::rollBack();
                    report($e);
                    return false;
                }
            }
            
            return false;
        });
        
        // 监听团队成员被移除后事件
        Event::listen(TeamMemberRemoved::class, function ($event) {
            $this->ensureUserHasTeam(User::find($event->teamMember->id));
        });
        
        // 监听团队成员被添加后事件
        Event::listen(TeamMemberAdded::class, function ($event) {
            $user = User::find($event->teamMember->id);
            $user->switchTeam($event->team);
        });
    }
    
    /**
     * 确保用户至少属于一个团队
     *
     * @param User $user
     */
    protected function ensureUserHasTeam($user): void
    {
        if (!$user) {
            return;
        }
        
        // 如果用户不属于任何团队，则将其添加到default团队(ID=2)
        if (count($user->allTeams()) === 0) {
            $defaultTeam = $this->getDefaultTeam();
            if ($defaultTeam) {
                $user->teams()->attach($defaultTeam);
                $user->switchTeam($defaultTeam);
            }
        }
    }
    
    /**
     * 获取default团队(ID=2)，使用缓存避免重复查询
     * 
     * @return \Laravel\Jetstream\Team|null
     */
    protected function getDefaultTeam()
    {
        if ($this->defaultTeam === null) {
            $this->defaultTeam = Jetstream::newTeamModel()->find(2);
        }
        
        return $this->defaultTeam;
    }
} 