<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserRoleController extends Controller
{
    /**
     * 获取用户的所有角色
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserRoles(Request $request, $userId)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $user = User::findOrFail($userId);
        
        // 刷新用户关联
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        return response()->json([
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * 为用户分配角色
     *
     * @param Request $request
     * @param int $userId
     * @return UserResource
     */
    public function assignRoles(Request $request, $userId)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);
        
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $user = User::findOrFail($userId);
        
        // 获取当前团队下的角色
        $roles = Role::whereIn('name', $request->roles)
            ->where('team_id', $teamId)
            ->get();
        
        // 分配角色给用户
        $user->syncRoles($roles);
        
        // 刷新用户关联
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        return new UserResource($user);
    }

    /**
     * 从用户移除角色
     *
     * @param Request $request
     * @param int $userId
     * @return UserResource
     */
    public function removeRoles(Request $request, $userId)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);
        
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $user = User::findOrFail($userId);
        
        // 获取当前团队下的角色
        $roles = Role::whereIn('name', $request->roles)
            ->where('team_id', $teamId)
            ->get();
        
        // 从用户移除角色
        foreach ($roles as $role) {
            $user->removeRole($role);
        }
        
        // 刷新用户关联
        $user->unsetRelation('roles')->unsetRelation('permissions');
        
        return new UserResource($user);
    }
} 