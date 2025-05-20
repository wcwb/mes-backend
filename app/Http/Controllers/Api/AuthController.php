<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;
use Laravel\Jetstream\Jetstream;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    /**
     * 处理用户注册请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 获取default团队(ID=2)
        // 注意：此团队在初始化时不分配任何角色和权限，保证新用户没有默认权限
        $defaultTeam = Jetstream::newTeamModel()->find(2);
        
        // 如果新用户没有分配到任何团队，则添加到default团队
        if ($defaultTeam && $user->current_team_id == null) {
            // 检查用户尚未分配团队时才分配default团队
            // 设置default团队为用户的当前团队，用户登录后将默认使用此团队的权限上下文
            // 由于default团队没有初始角色和权限，新用户将没有任何系统权限，需要管理员分配
            $user->teams()->attach($defaultTeam);
            
        }

        $deviceName = $request->device_name ?? $request->userAgent() ?? '未知设备';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'message' => '注册成功'
        ], 201);
    }

    /**
     * 处理用户登录请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['提供的凭证不正确'],
            ]);
        }

        $deviceName = $request->device_name ?? $request->userAgent() ?? '未知设备';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'message' => '登录成功'
        ]);
    }

    /**
     * 处理用户登出请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => '已成功登出']);
    }

    /**
     * 获取当前认证用户的信息
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        // 获取用户当前团队ID，如果没有则使用默认值(2)
        // 默认使用团队ID=2(default团队)确保用户始终在一个有效的权限上下文中
        $teamId = $request->user()->currentTeam ? $request->user()->currentTeam->id : 2;
        
        // 设置权限系统的团队ID
        // 这是必要的步骤，确保Spatie权限系统在正确的团队上下文中运行
        // 所有后续的权限检查(如hasPermissionTo(), hasRole()等)将基于此团队ID
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        // 确保关系被重新加载
        // 清除用户角色和权限的缓存，确保获取最新的权限信息
        $user = $request->user();
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        return response()->json([
            'user' => $user
        ]);
    }
}
