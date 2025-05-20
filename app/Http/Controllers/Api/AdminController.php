<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    /**
     * 创建一个新的控制器实例
     */
    public function __construct()
    {
        // 应用 'role:admin' 中间件到所有方法
        $this->middleware('role:admin')->only(['adminDashboard', 'systemSettings']);
        
        // 应用 'permission:管理用户' 中间件到 manageUsers 方法
        $this->middleware('permission:管理用户')->only('manageUsers');
        
        // 应用 'permission:管理系统设置' 中间件到 systemSettings 方法（与角色中间件叠加）
        $this->middleware('permission:管理系统设置')->only('systemSettings');
    }
    
    /**
     * 管理员仪表盘
     * 需要 'admin' 角色
     */
    public function adminDashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => '欢迎访问管理员仪表盘',
            'data' => [
                'stats' => [
                    'users' => 100,
                    'posts' => 500,
                    'comments' => 1500,
                ]
            ]
        ]);
    }
    
    /**
     * 用户管理功能
     * 需要 '管理用户' 权限
     */
    public function manageUsers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => '您可以管理用户',
            'data' => [
                'users' => [
                    ['id' => 1, 'name' => '张三', 'email' => 'zhangsan@example.com'],
                    ['id' => 2, 'name' => '李四', 'email' => 'lisi@example.com'],
                    ['id' => 3, 'name' => '王五', 'email' => 'wangwu@example.com'],
                ]
            ]
        ]);
    }
    
    /**
     * 系统设置
     * 需要 'admin' 角色和 '管理系统设置' 权限（同时满足）
     */
    public function systemSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => '您可以管理系统设置',
            'data' => [
                'settings' => [
                    'site_name' => '我的后台管理系统',
                    'maintenance_mode' => false,
                    'default_language' => 'zh_CN',
                    'timezone' => 'Asia/Shanghai',
                ]
            ]
        ]);
    }
} 