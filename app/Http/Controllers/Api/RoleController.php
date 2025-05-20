<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\RoleCreateRequest;
use App\Http\Requests\Role\RoleUpdateRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * 获取当前团队下的所有角色
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $roles = Role::where('team_id', $teamId)->get();
        
        return RoleResource::collection($roles);
    }

    /**
     * 创建新角色
     *
     * @param RoleCreateRequest $request
     * @return RoleResource
     */
    public function store(RoleCreateRequest $request)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
            'team_id' => $teamId,
        ]);
        
        // 如果请求中包含权限，则分配权限
        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('name', $request->permissions)
                ->where('team_id', $teamId)
                ->get();
            
            $role->syncPermissions($permissions);
        }
        
        return new RoleResource($role);
    }

    /**
     * 获取指定角色详情
     *
     * @param Request $request
     * @param int $id
     * @return RoleResource
     */
    public function show(Request $request, $id)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $role = Role::where('team_id', $teamId)->findOrFail($id);
        
        return new RoleResource($role);
    }

    /**
     * 更新指定角色
     *
     * @param RoleUpdateRequest $request
     * @param int $id
     * @return RoleResource
     */
    public function update(RoleUpdateRequest $request, $id)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $role = Role::where('team_id', $teamId)->findOrFail($id);
        
        // 更新角色名称
        if ($request->has('name')) {
            $role->name = $request->name;
            $role->save();
        }
        
        // 如果请求中包含权限，则同步权限
        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('name', $request->permissions)
                ->where('team_id', $teamId)
                ->get();
            
            $role->syncPermissions($permissions);
        }
        
        return new RoleResource($role);
    }

    /**
     * 删除指定角色
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $role = Role::where('team_id', $teamId)->findOrFail($id);
        $role->delete();
        
        return response()->json(['message' => '角色已删除']);
    }

    /**
     * 为角色分配权限
     *
     * @param Request $request
     * @param int $id
     * @return RoleResource
     */
    public function assignPermissions(Request $request, $id)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);
        
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $role = Role::where('team_id', $teamId)->findOrFail($id);
        
        $permissions = Permission::whereIn('name', $request->permissions)
            ->where('team_id', $teamId)
            ->get();
        
        $role->givePermissionTo($permissions);
        
        return new RoleResource($role);
    }

    /**
     * 从角色移除权限
     *
     * @param Request $request
     * @param int $id
     * @return RoleResource
     */
    public function removePermissions(Request $request, $id)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);
        
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam->id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $role = Role::where('team_id', $teamId)->findOrFail($id);
        
        $permissions = Permission::whereIn('name', $request->permissions)
            ->where('team_id', $teamId)
            ->get();
        
        $role->revokePermissionTo($permissions);
        
        return new RoleResource($role);
    }
} 