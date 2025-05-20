<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

class OrdersController extends Controller
{
    /**
     * 显示订单列表
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // 确保使用当前团队 ID
        $teamId = Auth::user()->currentTeam ? Auth::user()->currentTeam->id : 2;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        // 检查用户是否有查看订单的权限
        if (!Auth::user()->can('view_orders')) {
            abort(403, '您没有查看订单的权限');
        }
        
        // 有权限时返回成功信息
        return response()->json([
            'message' => '有权限查看订单'
        ]);
    }
} 