<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Jetstream\Jetstream;
use Symfony\Component\HttpFoundation\Response;

class PreventTeamLeaving
{
    /**
     * 处理传入的请求
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 只处理已登录用户的请求
        if (!auth()->check()) {
            return $next($request);
        }
        
        // 获取可能有的团队ID参数
        $teamId = $this->extractTeamId($request);

        if ($teamId) {
            $team = Jetstream::newTeamModel()->find($teamId);
            
            if (!$team) {
                Log::warning('尝试访问不存在的团队', [
                    'user_id' => auth()->id(),
                    'team_id' => $teamId,
                    'request_path' => $request->path()
                ]);
                
                // 团队不存在时返回404
                if ($request->expectsJson()) {
                    return response()->json(['message' => '团队不存在'], 404);
                }
                
                return $next($request);
            }
            
            // 如果是团队拥有者，则允许操作
            if ($team->user_id === auth()->id()) {
                return $next($request);
            }
            
            // 检测是否为退出团队的操作
            if ($this->isLeavingTeamRequest($request, $teamId)) {
                Log::warning('普通成员尝试退出团队', [
                    'user_id' => auth()->id(),
                    'team_id' => $teamId,
                    'request_path' => $request->path(),
                    'method' => $request->method()
                ]);
                
                $message = '普通成员不能退出团队，请联系团队拥有者';
                
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 403);
                }
                
                return redirect()->back()->with('error', $message);
            }
        }
        
        return $next($request);
    }
    
    /**
     * 从请求中提取团队ID
     *
     * @param Request $request
     * @return int|null
     */
    protected function extractTeamId(Request $request): ?int
    {
        // 从路由参数中提取
        if ($request->route('team') && is_object($request->route('team'))) {
            return $request->route('team')->id;
        }
        
        // 从其他可能的参数中提取
        foreach (['teamId', 'team_id'] as $param) {
            if ($request->route($param)) {
                return (int) $request->route($param);
            }
            
            if ($request->input($param)) {
                return (int) $request->input($param);
            }
        }
        
        // 从API路径中提取
        $path = $request->path();
        if (preg_match('/api\/teams\/(\d+)/', $path, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
    
    /**
     * 检查请求是否为退出团队的操作
     *
     * @param Request $request
     * @param int $teamId
     * @return bool
     */
    protected function isLeavingTeamRequest(Request $request, int $teamId): bool
    {
        if (!$request->isMethod('delete')) {
            return false;
        }
        
        // 检查常见的离开团队路由
        $leavingRoutes = [
            'team-members.destroy',
            'teams.destroy',
            'api.team-members.destroy'
        ];
        
        foreach ($leavingRoutes as $route) {
            if ($request->routeIs($route)) {
                return true;
            }
        }
        
        // 检查API路径
        $path = $request->path();
        if (strpos($path, 'api/teams/' . $teamId) === 0 || 
            strpos($path, 'api/team-members') === 0) {
            return true;
        }
        
        return false;
    }
} 