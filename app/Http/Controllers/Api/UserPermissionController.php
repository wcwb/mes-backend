<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UserPermissionController extends Controller
{
    /**
     * 获取用户的所有权限（包括直接权限和通过角色获得的权限）
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPermissions(Request $request, $userId)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $user = User::findOrFail($userId);
        
        // 刷新用户关联
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        return response()->json([
            'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
            'all_permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * 为用户直接分配权限（不通过角色）
     *
     * @param Request $request
     * @param int $userId
     * @return UserResource
     */
    public function assignDirectPermissions(Request $request, $userId)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);
        
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $user = User::findOrFail($userId);
        
        // 获取当前团队下的权限
        $permissions = Permission::whereIn('name', $request->permissions)
            ->where('team_id', $teamId)
            ->get();
        
        // 直接分配权限给用户
        $user->givePermissionTo($permissions);
        
        // 刷新用户关联
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        return new UserResource($user);
    }

    /**
     * 从用户移除直接权限
     *
     * @param Request $request
     * @param int $userId
     * @return UserResource
     */
    public function removeDirectPermissions(Request $request, $userId)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);
        
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $user = User::findOrFail($userId);
        
        // 获取当前团队下的权限
        $permissions = Permission::whereIn('name', $request->permissions)
            ->where('team_id', $teamId)
            ->get();
        
        // 从用户移除直接权限
        $user->revokePermissionTo($permissions);
        
        // 刷新用户关联
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        return new UserResource($user);
    }

    /**
     * 检查用户是否有特定权限
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPermission(Request $request, $userId)
    {
        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);
        
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $user = User::findOrFail($userId);
        
        // 刷新用户关联
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        return response()->json([
            'has_permission' => $user->hasPermissionTo($request->permission),
        ]);
    }
} 