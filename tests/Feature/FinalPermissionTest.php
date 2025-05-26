<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

// 启动 Laravel 应用
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "=== 最终测试脚本 ===" . PHP_EOL;
echo "测试目标：为用户ID=3分配团队ID=10的creator角色，并验证view_orders权限" . PHP_EOL;
echo "开始时间: " . now()->format('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

try {
    $userId = 3;
    $teamId = 10;
    $roleName = 'creator';
    $permissionName = 'view_orders';

    // 步骤1：获取用户和团队
    echo "步骤1：获取用户和团队" . PHP_EOL;
    $user = User::find($userId);
    $team = Team::find($teamId);

    if (!$user || !$team) {
        throw new Exception("用户或团队不存在");
    }

    echo "  用户: {$user->name} (ID: {$user->id})" . PHP_EOL;
    echo "  团队: {$team->name} (ID: {$team->id})" . PHP_EOL . PHP_EOL;

    // 步骤2：设置用户当前团队
    echo "步骤2：设置用户当前团队" . PHP_EOL;
    $user->current_team_id = $teamId;
    $user->save();
    echo "  ✅ 用户当前团队设置为: {$teamId}" . PHP_EOL . PHP_EOL;

    // 步骤3：确保用户在团队中
    echo "步骤3：确保用户在团队中" . PHP_EOL;
    if (!$user->teams()->where('team_id', $teamId)->exists()) {
        $user->teams()->attach($teamId, ['role' => $roleName]);
        echo "  ✅ 用户已添加到团队" . PHP_EOL;
    } else {
        $user->teams()->updateExistingPivot($teamId, ['role' => $roleName]);
        echo "  ✅ 用户团队角色已更新" . PHP_EOL;
    }
    echo PHP_EOL;

    // 步骤4：设置权限系统团队上下文
    echo "步骤4：设置权限系统团队上下文" . PHP_EOL;
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($teamId);
    echo "  ✅ 权限系统团队上下文设置为: {$teamId}" . PHP_EOL . PHP_EOL;

    // 步骤5：分配角色
    echo "步骤5：分配Spatie权限角色" . PHP_EOL;
    $role = Role::where('name', $roleName)->where('team_id', $teamId)->first();

    if (!$role) {
        throw new Exception("角色 '{$roleName}' 在团队 {$teamId} 中不存在");
    }

    // 移除用户在当前团队的所有角色
    $user->roles()->where('roles.team_id', $teamId)->detach();

    // 分配新角色
    $user->assignRole($role);
    echo "  ✅ 已分配角色: {$roleName}" . PHP_EOL . PHP_EOL;

    // 步骤6：验证权限
    echo "步骤6：权限验证" . PHP_EOL;

    // 重新加载用户
    $user = $user->fresh(['roles', 'permissions']);

    // 获取权限对象
    $permission = Permission::where('name', $permissionName)->where('team_id', $teamId)->first();

    if (!$permission) {
        throw new Exception("权限 '{$permissionName}' 在团队 {$teamId} 中不存在");
    }

    echo "  权限对象: {$permission->name} (ID: {$permission->id}, 团队: {$permission->team_id})" . PHP_EOL;

    // 多种方式检查权限
    $checks = [
        'hasPermissionTo(对象)' => $user->hasPermissionTo($permission),
        'hasPermissionTo(名称)' => $user->hasPermissionTo($permissionName),
        'can(名称)' => $user->can($permissionName),
    ];

    foreach ($checks as $method => $result) {
        $status = $result ? '✅' : '❌';
        echo "  {$status} {$method}: " . ($result ? '有权限' : '无权限') . PHP_EOL;
    }
    echo PHP_EOL;

    // 步骤7：角色权限检查
    echo "步骤7：角色权限检查" . PHP_EOL;
    $roleHasPermission = $role->hasPermissionTo($permission);
    $status = $roleHasPermission ? '✅' : '❌';
    echo "  {$status} 角色 '{$roleName}' 是否有权限 '{$permissionName}': " . ($roleHasPermission ? '是' : '否') . PHP_EOL . PHP_EOL;

    // 步骤8：最终状态总结
    echo "步骤8：最终状态总结" . PHP_EOL;
    echo "  用户ID: {$user->id}" . PHP_EOL;
    echo "  用户名: {$user->name}" . PHP_EOL;
    echo "  当前团队: {$user->current_team_id}" . PHP_EOL;

    $userRoles = $user->roles()->where('roles.team_id', $teamId)->pluck('name')->toArray();
    echo "  Spatie角色: " . implode(', ', $userRoles) . PHP_EOL;

    $teamRole = $user->teams()->where('team_id', $teamId)->first();
    echo "  团队角色: " . ($teamRole ? $teamRole->pivot->role : '无') . PHP_EOL;

    echo PHP_EOL . "🎉 测试完成！" . PHP_EOL;
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . PHP_EOL;
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
} catch (Throwable $e) {
    echo "❌ 严重错误: " . $e->getMessage() . PHP_EOL;
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}

echo PHP_EOL . "结束时间: " . now()->format('Y-m-d H:i:s') . PHP_EOL;
echo "=== 测试结束 ===" . PHP_EOL;
