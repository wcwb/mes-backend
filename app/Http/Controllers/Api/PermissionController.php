<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\PermissionCreateRequest;
use App\Http\Requests\Permission\PermissionUpdateRequest;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    /**
     * 获取当前团队下的所有权限
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam ? $request->user()->currentTeam->id : 2;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $permissions = Permission::where('team_id', $teamId)->get();
        
        return PermissionResource::collection($permissions);
    }

    /**
     * 创建新权限
     *
     * @param PermissionCreateRequest $request
     * @return PermissionResource
     */
    public function store(PermissionCreateRequest $request)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam ? $request->user()->currentTeam->id : 2;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => 'web',
            'team_id' => $teamId,
        ]);
        
        return new PermissionResource($permission);
    }

    /**
     * 获取指定权限详情
     *
     * @param Request $request
     * @param int $id
     * @return PermissionResource
     */
    public function show(Request $request, $id)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam ? $request->user()->currentTeam->id : 2;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $permission = Permission::findOrFail($id);
        
        return new PermissionResource($permission);
    }

    /**
     * 更新权限
     *
     * @param PermissionUpdateRequest $request
     * @param int $id
     * @return PermissionResource
     */
    public function update(PermissionUpdateRequest $request, $id)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam ? $request->user()->currentTeam->id : 2;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $permission = Permission::findOrFail($id);
        $permission->update([
            'name' => $request->name,
        ]);
        
        return new PermissionResource($permission);
    }

    /**
     * 删除权限
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        // 确保使用当前团队 ID
        $teamId = $request->user()->currentTeam ? $request->user()->currentTeam->id : 2;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        $permission = Permission::findOrFail($id);
        $permission->delete();
        
        return response()->json([
            'message' => '权限已成功删除'
        ]);
    }
} 