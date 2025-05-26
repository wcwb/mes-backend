<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class switchTeamController extends Controller
{
    /**
     * 切换当前团队
     */
    public function update(Request $request)
    {
        // return $request->all();

        $request->validate([
            'team_id' => 'required|exists:teams,id',
        ]);

        $team = Team::findOrFail($request->team_id);

        // 验证用户是否属于该团队
        if (! $request->user()->belongsToTeam($team)) {
            return response()->json(['message' => '无权切换到该团队'], 403);
        }

        // 切换当前团队
        $request->user()->switchTeam($team);

        app(PermissionRegistrar::class)->setPermissionsTeamId($team->id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => new \App\Http\Resources\UserResource($request->user())
            ],
            'message' => '团队切换成功'
        ]);
    }
}
