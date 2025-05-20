<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\AddsTeamMembers;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Rules\Role;

class AddTeamMember implements AddsTeamMembers
{
    /**
     * 直接添加团队成员到指定团队
     *
     * @param  \App\Models\User  $user  当前操作的用户（团队拥有者）
     * @param  \App\Models\Team  $team  目标团队
     * @param  string  $email  被添加成员的邮箱
     * @param  string|null  $role  分配的角色
     * @return void
     */
    public function add(User $user, Team $team, string $email, string $role = null): void
    {
        try {
            // 记录操作开始日志
            Log::channel('team_management')->info('开始添加团队成员', [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'acting_user_id' => $user->id,
                'target_email' => $this->maskEmail($email),
                'role' => $role
            ]);
            
            // 确保当前用户是团队拥有者
            if ($team->user_id !== $user->id) {
                Log::channel('team_management')->warning('非团队拥有者尝试添加成员', [
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'team_owner_id' => $team->user_id,
                    'remote_ip' => request()->ip()
                ]);
                
                throw ValidationException::withMessages([
                    'email' => __('只有团队拥有者可以添加成员.'),
                ]);
            }

            // 验证用户是否有权限添加团队成员
            try {
                Gate::forUser($user)->authorize('addTeamMember', $team);
            } catch (\Exception $e) {
                Log::channel('team_management')->warning('用户无权限添加团队成员', [
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'exception' => $e->getMessage()
                ]);
                
                throw ValidationException::withMessages([
                    'email' => __('您没有权限添加团队成员.'),
                ]);
            }

            // 查找要添加的用户
            $addedUser = User::where('email', $email)->first();
            
            if (!$addedUser) {
                Log::channel('team_management')->info('尝试添加不存在的用户', [
                    'email' => $this->maskEmail($email),
                    'team_id' => $team->id
                ]);
                
                throw ValidationException::withMessages([
                    'email' => __('未找到该邮箱对应的用户.'),
                ]);
            }
            
            // 验证角色
            try {
                $this->validateRole($role);
            } catch (ValidationException $e) {
                Log::channel('team_management')->warning('角色验证失败', [
                    'role' => $role,
                    'team_id' => $team->id,
                    'errors' => $e->errors()
                ]);
                
                throw $e;
            }
            
            // 检查用户是否已经是团队成员
            if ($addedUser->belongsToTeam($team)) {
                Log::channel('team_management')->info('尝试添加已存在的团队成员', [
                    'user_id' => $addedUser->id,
                    'team_id' => $team->id
                ]);
                
                throw ValidationException::withMessages([
                    'email' => __('该用户已是团队成员.'),
                ]);
            }
            
            // 事务处理
            DB::beginTransaction();
            
            try {
                // 获取邀请者的所有团队
                $userTeams = $user->allTeams();
                
                // 获取default团队(ID=2)
                $defaultTeam = Jetstream::newTeamModel()->find(2);
                
                if (!$defaultTeam) {
                    Log::channel('team_management')->error('找不到default团队', [
                        'expected_id' => 2,
                    ]);
                }
                
                // 如果邀请者只有一个团队，则只能从default团队添加用户
                if (count($userTeams) === 1 && $defaultTeam) {
                    $userExists = User::where('email', $email)
                        ->whereHas('teams', function ($query) use ($defaultTeam) {
                            $query->where('team_id', $defaultTeam->id);
                        })
                        ->exists();
                        
                    if (!$userExists) {
                        Log::channel('team_management')->warning('尝试从非default团队添加用户', [
                            'user_id' => $addedUser->id,
                            'user_teams' => $addedUser->teams()->pluck('team_id')->toArray()
                        ]);
                        
                        throw ValidationException::withMessages([
                            'email' => __('您只能从default团队添加用户.'),
                        ]);
                    }
                }
                
                // 触发添加团队成员前事件
                $eventResult = AddingTeamMember::dispatch($team, $addedUser);
                if ($eventResult === false) {
                    Log::channel('team_management')->warning('添加团队成员事件被拒绝', [
                        'user_id' => $addedUser->id,
                        'team_id' => $team->id
                    ]);
                    
                    throw ValidationException::withMessages([
                        'email' => __('添加用户失败，请检查用户权限.'),
                    ]); 
                }
                
                // 执行添加操作
                $team->users()->attach($addedUser, ['role' => $role]);
                
                // 触发团队成员已添加事件
                TeamMemberAdded::dispatch($team, $addedUser);
                
                // 记录日志
                Log::channel('team_management')->info('团队成员添加成功', [
                    'team_id' => $team->id,
                    'added_by' => $user->id,
                    'user_id' => $addedUser->id,
                    'role' => $role
                ]);
                
                DB::commit();
            } catch (ValidationException $e) {
                DB::rollBack();
                throw $e;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::channel('team_management')->error('添加团队成员事务处理失败', [
                    'team_id' => $team->id,
                    'user_to_add' => $addedUser->id,
                    'error' => $e->getMessage(),
                    'trace' => $this->formatStackTraceForLog($e)
                ]);
                
                throw ValidationException::withMessages([
                    'email' => __('系统错误，无法添加团队成员，请稍后重试.'),
                ]);
            }
        } catch (ValidationException $e) {
            // 验证错误直接抛出，让控制器处理
            throw $e;
        } catch (\Exception $e) {
            // 记录其他未预期的异常
            Log::channel('team_management')->error('添加团队成员时发生未处理的异常', [
                'team_id' => $team->id ?? null,
                'email' => $this->maskEmail($email),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $this->formatStackTraceForLog($e)
            ]);
            
            // 用户友好的错误消息
            throw ValidationException::withMessages([
                'email' => __('处理请求时发生错误，请稍后重试.'),
            ]);
        }
    }
    
    /**
     * 验证角色是否有效
     *
     * @param  string|null  $role
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRole(?string $role): void
    {
        if (! Jetstream::hasRoles()) {
            return;
        }
        
        $role = $role ?? '';
        
        $validator = validator(['role' => $role], [
            'role' => ['required', 'string', new Role],
        ]);
        
        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'role' => __('给定的角色无效.'),
            ]);
        }
    }
    
    /**
     * 隐藏电子邮件中间部分，保护隐私
     *
     * @param string $email
     * @return string
     */
    protected function maskEmail(string $email): string
    {
        if (empty($email)) {
            return '';
        }
        
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***.com';
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        
        // 保留用户名的前两个和最后一个字符，其余用*代替
        $len = strlen($name);
        if ($len <= 3) {
            $maskedName = $name;
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', $len - 3) . substr($name, -1);
        }
        
        return $maskedName . '@' . $domain;
    }
    
    /**
     * 格式化堆栈跟踪，仅保留关键信息用于日志记录
     *
     * @param \Exception $exception
     * @return array
     */
    protected function formatStackTraceForLog(\Exception $exception): array
    {
        $trace = $exception->getTrace();
        $formattedTrace = [];
        
        // 只取前5个堆栈信息，避免日志过大
        $limit = min(5, count($trace));
        
        for ($i = 0; $i < $limit; $i++) {
            $item = $trace[$i];
            $formattedTrace[] = [
                'file' => $item['file'] ?? 'unknown',
                'line' => $item['line'] ?? 0,
                'function' => ($item['class'] ?? '') . ($item['type'] ?? '') . ($item['function'] ?? ''),
            ];
        }
        
        return $formattedTrace;
    }
} 