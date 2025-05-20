<?php

namespace App\Http\Middleware;

use App\Helpers\TeamConstants;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Jetstream\Jetstream;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasTeam
{
    /**
     * 处理传入的请求
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if ($user && count($user->allTeams()) === 0) {
            try {
                // 用户没有任何团队，将其添加到default团队
                $defaultTeam = Jetstream::newTeamModel()->find(TeamConstants::DEFAULT_TEAM_ID);
                
                if ($defaultTeam) {
                    // 添加用户到团队并切换到该团队
                    $user->teams()->attach($defaultTeam);
                    $user->switchTeam($defaultTeam);
                    
                    // 记录添加用户到default团队的日志
                    Log::info('用户已自动添加到default团队', [
                        'user_id' => $user->id,
                        'team_id' => $defaultTeam->id
                    ]);
                } else {
                    // 如果default团队不存在，记录错误
                    Log::error('未找到default团队，无法将用户添加到团队');
                    
                    // 可选：在开发环境中显示警告
                    if (config('app.debug')) {
                        return response()->json([
                            'message' => '系统错误：未找到default团队，请联系管理员'
                        ], 500);
                    }
                }
            } catch (\Exception $e) {
                Log::error('将用户添加到default团队时出错', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $next($request);
    }
} 