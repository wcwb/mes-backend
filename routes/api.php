<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserPermissionController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\Api\SwitchTeamController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// 自定义认证路由
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// 需要认证的路由
Route::middleware('auth:sanctum')->group(function () {
    // 用户信息与登出
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // 订单路由
    Route::get('/orders', [OrdersController::class, 'index']);
    
    Route::middleware('ensure.team')->group(function () {
        Route::apiResource('teams', TeamController::class);
    });

    // 团队管理路由
    Route::prefix('teams-member')->group(function () {
        // 团队成员管理
        Route::get('/{teamId}/members', [TeamMemberController::class, 'index'])->name('api.team-members.index');
        Route::post('/{teamId}/members', [TeamMemberController::class, 'store'])->name('api.team-members.store');
        Route::put('/{teamId}/members/{userId}', [TeamMemberController::class, 'update'])->name('api.team-members.update');
        Route::delete('/{teamId}/members/{userId}', [TeamMemberController::class, 'destroy'])->name('api.team-members.destroy');
    });

    Route::put('/switch-team', [SwitchTeamController::class, 'update']);
    
    // 角色管理
    Route::apiResource('roles', RoleController::class);
    Route::post('/roles/{role}/permissions', [RoleController::class, 'assignPermissions']);
    Route::delete('/roles/{role}/permissions', [RoleController::class, 'removePermissions']);
    
    // 权限管理
    Route::apiResource('permissions', PermissionController::class);
    
    // 用户角色管理
    Route::get('/users/{user}/roles', [UserRoleController::class, 'getUserRoles']);
    Route::post('/users/{user}/roles', [UserRoleController::class, 'assignRoles']);
    Route::delete('/users/{user}/roles', [UserRoleController::class, 'removeRoles']);
    
    // 用户权限管理
    Route::get('/users/{user}/permissions', [UserPermissionController::class, 'getUserPermissions']);
    Route::post('/users/{user}/permissions', [UserPermissionController::class, 'assignDirectPermissions']);
    Route::delete('/users/{user}/permissions', [UserPermissionController::class, 'removeDirectPermissions']);
    Route::get('/users/{user}/check-permission', [UserPermissionController::class, 'checkPermission']);
    
    // 管理员控制器路由
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'adminDashboard']);
        Route::get('/users', [AdminController::class, 'manageUsers']);
        Route::get('/settings', [AdminController::class, 'systemSettings']);
    });
    
    // 测试超级管理员权限跳过
    Route::get('/test-super-admin', function () {
        return response()->json([
            'success' => true,
            'message' => '您成功访问了受保护的资源',
            'user' => request()->user()->only(['id', 'name', 'email']),
            'is_super_admin' => \App\Helpers\PermissionHelper::isSuperAdmin(),
        ]);
    })->middleware('permission:不存在的权限'); // 使用一个不存在的权限进行测试
}); 