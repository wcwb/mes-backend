<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\AddsTeamMembers;
use Laravel\Jetstream\Contracts\RemovesTeamMembers;
use Laravel\Jetstream\Jetstream;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TeamMemberController extends Controller
{
    /**
     * 显示团队成员列表
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $teamId)
    {
        try {
            // 查找团队
            try {
                $team = Jetstream::newTeamModel()->findOrFail($teamId);
            } catch (ModelNotFoundException $e) {
                Log::warning('尝试访问不存在的团队', [
                    'team_id' => $teamId,
                    'user_id' => $request->user()->id ?? null,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'message' => __('团队不存在')
                ], 404);
            }
            
            // 检查用户权限
            if (!$request->user()->belongsToTeam($team)) {
                Log::warning('用户尝试访问无权限的团队', [
                    'team_id' => $teamId,
                    'user_id' => $request->user()->id,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'message' => __('您无权查看该团队成员')
                ], 403);
            }
            
            $response = [
                'team' => $team->load('owner'),
                'users' => $team->allUsers(),
                'availableRoles' => array_values(Jetstream::$roles),
                'userRole' => $team->userRole($request->user()),
            ];
            
            Log::info('成功获取团队成员列表', [
                'team_id' => $teamId,
                'user_id' => $request->user()->id,
                'member_count' => count($response['users'])
            ]);
            
            return response()->json($response);
        } catch (Throwable $e) {
            Log::error('获取团队成员列表失败', [
                'team_id' => $teamId,
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $this->formatStackTrace($e)
            ]);
            
            return response()->json([
                'message' => __('获取团队成员列表失败，请稍后重试')
            ], 500);
        }
    }
    
    /**
     * 添加团队成员
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $teamId)
    {
        Log::info('接收添加团队成员请求', [
            'team_id' => $teamId,
            'user_id' => $request->user()->id ?? null,
            'ip' => $request->ip()
        ]);
        
        try {
            // 查找团队
            try {
                $team = Jetstream::newTeamModel()->findOrFail($teamId);
            } catch (ModelNotFoundException $e) {
                Log::warning('尝试向不存在的团队添加成员', [
                    'team_id' => $teamId,
                    'user_id' => $request->user()->id ?? null
                ]);
                
                return response()->json([
                    'message' => __('团队不存在')
                ], 404);
            }
            
            // 确保当前用户是团队拥有者
            if ($team->user_id !== $request->user()->id) {
                Log::warning('非团队拥有者尝试添加成员', [
                    'team_id' => $team->id,
                    'user_id' => $request->user()->id,
                    'owner_id' => $team->user_id
                ]);
                
                return response()->json([
                    'message' => __('只有团队拥有者可以添加成员')
                ], 403);
            }
            
            // 验证请求数据
            try {
                $validated = $request->validate([
                    'email' => ['required', 'email'],
                    'role' => ['required', 'string'],
                ]);
            } catch (ValidationException $e) {
                Log::info('团队成员添加验证失败', [
                    'team_id' => $team->id,
                    'errors' => $e->errors(),
                    'user_id' => $request->user()->id
                ]);
                
                return response()->json([
                    'message' => __('提供的数据无效'),
                    'errors' => $e->errors()
                ], 422);
            }
            
            // 使用AddsTeamMembers服务添加团队成员
            try {
                app(AddsTeamMembers::class)->add(
                    $request->user(),
                    $team,
                    $validated['email'],
                    $validated['role']
                );
                
                Log::info('团队成员添加成功', [
                    'team_id' => $team->id,
                    'added_by' => $request->user()->id,
                    'email' => $this->maskEmail($validated['email']),
                    'role' => $validated['role']
                ]);
                
                return response()->json([
                    'message' => __('成员已成功添加到团队'),
                ]);
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => __('添加成员失败'),
                    'errors' => $e->errors()
                ], 422);
            }
        } catch (ValidationException $e) {
            // 这里单独处理ValidationException，因为它已经包含了格式化好的错误信息
            return response()->json([
                'message' => __('验证错误'),
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            // 处理权限异常
            Log::warning('团队成员添加权限错误', [
                'team_id' => $teamId,
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => $e->getMessage() ?: __('您没有执行此操作的权限'),
            ], 403);
        } catch (Throwable $e) {
            // 记录未预期的异常
            Log::error('添加团队成员时出现未捕获异常', [
                'team_id' => $teamId,
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $this->formatStackTrace($e)
            ]);
            
            // 返回用户友好的错误消息
            return response()->json([
                'message' => __('处理请求时发生错误，请稍后重试'),
            ], 500);
        }
    }
    
    /**
     * 移除团队成员
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @param  int  $userId
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $teamId, $userId)
    {
        Log::info('接收移除团队成员请求', [
            'team_id' => $teamId,
            'user_id' => $request->user()->id ?? null,
            'target_user_id' => $userId,
            'ip' => $request->ip()
        ]);
        
        try {
            // 查找团队
            try {
                $team = Jetstream::newTeamModel()->findOrFail($teamId);
            } catch (ModelNotFoundException $e) {
                Log::warning('尝试从不存在的团队移除成员', [
                    'team_id' => $teamId,
                    'user_id' => $request->user()->id ?? null
                ]);
                
                return response()->json([
                    'message' => __('团队不存在')
                ], 404);
            }
            
            // 查找用户
            try {
                $user = User::findOrFail($userId);
            } catch (ModelNotFoundException $e) {
                Log::warning('尝试移除不存在的用户', [
                    'team_id' => $teamId,
                    'target_user_id' => $userId,
                    'user_id' => $request->user()->id ?? null
                ]);
                
                return response()->json([
                    'message' => __('用户不存在')
                ], 404);
            }
            
            // 使用RemovesTeamMembers服务移除团队成员
            try {
                app(RemovesTeamMembers::class)->remove(
                    $request->user(),
                    $team,
                    $user
                );
                
                Log::info('团队成员移除成功', [
                    'team_id' => $team->id,
                    'removed_by' => $request->user()->id,
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'message' => __('成员已成功从团队中移除'),
                ]);
            } catch (ValidationException $e) {
                Log::warning('移除团队成员验证失败', [
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'errors' => $e->errors()
                ]);
                
                return response()->json([
                    'message' => __('移除成员失败'),
                    'errors' => $e->errors()
                ], 422);
            } catch (AuthorizationException $e) {
                Log::warning('移除团队成员权限错误', [
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'message' => $e->getMessage() ?: __('您没有执行此操作的权限'),
                ], 403);
            }
        } catch (Throwable $e) {
            Log::error('移除团队成员时出现未捕获异常', [
                'team_id' => $teamId,
                'user_id' => $userId,
                'removed_by' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $this->formatStackTrace($e)
            ]);
            
            return response()->json([
                'message' => __('处理请求时发生错误，请稍后重试'),
            ], 500);
        }
    }
    
    /**
     * 更新团队成员角色
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @param  int  $userId
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $teamId, $userId)
    {
        Log::info('接收更新团队成员角色请求', [
            'team_id' => $teamId,
            'user_id' => $request->user()->id ?? null,
            'target_user_id' => $userId,
            'ip' => $request->ip()
        ]);
        
        try {
            // 查找团队
            try {
                $team = Jetstream::newTeamModel()->findOrFail($teamId);
            } catch (ModelNotFoundException $e) {
                Log::warning('尝试更新不存在团队的成员角色', [
                    'team_id' => $teamId,
                    'user_id' => $request->user()->id ?? null
                ]);
                
                return response()->json([
                    'message' => __('团队不存在')
                ], 404);
            }
            
            // 查找用户
            try {
                $user = User::findOrFail($userId);
            } catch (ModelNotFoundException $e) {
                Log::warning('尝试更新不存在用户的角色', [
                    'team_id' => $teamId,
                    'target_user_id' => $userId,
                    'user_id' => $request->user()->id ?? null
                ]);
                
                return response()->json([
                    'message' => __('用户不存在')
                ], 404);
            }
            
            // 确保当前用户是团队拥有者
            if ($team->user_id !== $request->user()->id) {
                Log::warning('非团队拥有者尝试更新成员角色', [
                    'team_id' => $team->id,
                    'user_id' => $request->user()->id,
                    'owner_id' => $team->user_id,
                    'target_user_id' => $user->id
                ]);
                
                return response()->json([
                    'message' => __('只有团队拥有者可以更新成员角色')
                ], 403);
            }
            
            // 验证请求数据
            try {
                $validated = $request->validate([
                    'role' => ['required', 'string'],
                ]);
            } catch (ValidationException $e) {
                Log::info('团队成员角色更新验证失败', [
                    'team_id' => $team->id,
                    'errors' => $e->errors(),
                    'user_id' => $request->user()->id,
                    'target_user_id' => $user->id
                ]);
                
                return response()->json([
                    'message' => __('提供的数据无效'),
                    'errors' => $e->errors()
                ], 422);
            }
            
            // 确保不能更改团队拥有者的角色
            if ($user->id === $team->user_id) {
                Log::warning('尝试更改团队拥有者角色', [
                    'team_id' => $team->id,
                    'user_id' => $request->user()->id,
                    'target_user_id' => $user->id
                ]);
                
                return response()->json([
                    'message' => __('不能更改团队拥有者的角色')
                ], 403);
            }
            
            // 验证角色是否有效
            $validRoles = collect(Jetstream::$roles)->pluck('key')->toArray();
            if (!in_array($validated['role'], $validRoles)) {
                Log::warning('尝试使用无效的角色', [
                    'team_id' => $team->id,
                    'user_id' => $request->user()->id,
                    'target_user_id' => $user->id,
                    'provided_role' => $validated['role'],
                    'valid_roles' => $validRoles
                ]);
                
                return response()->json([
                    'message' => __('提供的角色无效'),
                    'errors' => [
                        'role' => [__('提供的角色无效')]
                    ]
                ], 422);
            }
            
            try {
                // 使用数据库事务确保操作原子性
                DB::beginTransaction();
                
                // 更新角色
                $team->users()->updateExistingPivot($user->id, [
                    'role' => $validated['role'],
                ]);
                
                DB::commit();
                
                Log::info('团队成员角色已更新', [
                    'team_id' => $team->id,
                    'updated_by' => $request->user()->id,
                    'user_id' => $user->id,
                    'new_role' => $validated['role']
                ]);
                
                return response()->json([
                    'message' => __('成员角色已成功更新'),
                ]);
            } catch (Throwable $e) {
                DB::rollBack();
                
                Log::error('更新团队成员角色失败', [
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'role' => $validated['role'],
                    'error' => $e->getMessage(),
                    'trace' => $this->formatStackTrace($e)
                ]);
                
                throw $e;
            }
        } catch (Throwable $e) {
            Log::error('更新团队成员角色时出现未捕获异常', [
                'team_id' => $teamId,
                'user_id' => $userId,
                'updated_by' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $this->formatStackTrace($e)
            ]);
            
            return response()->json([
                'message' => __('处理请求时发生错误，请稍后重试'),
            ], 500);
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
     * 格式化堆栈跟踪，用于日志记录
     *
     * @param \Throwable $exception
     * @return array
     */
    protected function formatStackTrace(Throwable $exception): array
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