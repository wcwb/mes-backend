# 团队权限系统使用指南

## 1. 系统架构概述

本系统集成了Laravel Jetstream团队功能和Spatie权限管理系统，实现了基于团队的权限控制。主要组件包括：

- **Laravel Jetstream**: 提供团队管理基础设施
- **Spatie Permission**: 提供RBAC(基于角色的访问控制)功能
- **自定义中间件**: 确保权限检查在正确的团队上下文中执行

### 架构设计

```
用户 (User) <---> 团队 (Team) <---> 角色 (Role) <---> 权限 (Permission)
```

- 每个用户可以属于多个团队
- 用户在不同团队中可以拥有不同的角色和权限
- 系统会根据用户当前所在团队动态调整权限上下文

## 2. 核心概念

### 特殊团队

系统定义了两个特殊团队：

- **管理员团队(ID=1)**: 用于管理整个系统，超级管理员角色在此团队中
- **默认团队(ID=2)**: 所有新用户默认被添加到此团队

### 团队上下文

权限检查始终在特定团队上下文中执行。用户当前的团队决定了其可用的权限和角色。

### 常量定义

系统中的团队ID和角色名称通过`TeamConstants`类统一管理：

```php
TeamConstants::ADMIN_TEAM_ID    // 管理员团队ID
TeamConstants::DEFAULT_TEAM_ID  // 默认团队ID
TeamConstants::SUPER_ADMIN_ROLE // 超级管理员角色名称
```

## 3. 权限设计指南

### 权限命名约定

权限应按照`{动作}_{资源}`的格式命名，例如：

- `view_orders`: 查看订单
- `create_orders`: 创建订单
- `update_orders`: 更新订单
- `delete_orders`: 删除订单

### 角色设计

基础角色建议：

1. **viewer**: 只读权限
2. **creator**: 创建和查看权限
3. **editor**: 编辑、创建和查看权限
4. **owner**: 所有权限，包括删除

每个团队可以根据业务需求定制角色。

## 4. 中间件使用

系统提供了多个中间件用于权限控制：

### 团队权限中间件

```php
// 在路由中检查权限
Route::get('/orders', [OrderController::class, 'index'])
    ->middleware('permission:view_orders');

// 在路由中检查角色
Route::post('/orders', [OrderController::class, 'store'])
    ->middleware('role:creator');

// 组合使用
Route::group(['middleware' => ['permission:view_orders']], function () {
    // 这里的路由都需要view_orders权限
});
```

### 用户团队中间件

```php
// 确保用户有团队
Route::group(['middleware' => ['ensure.team']], function () {
    // 这些路由需要用户属于至少一个团队
});
```

## 5. 在代码中使用

### 控制器中使用

```php
public function update(Request $request, $id)
{
    // 检查权限
    if (!$request->user()->can('update_orders')) {
        return response()->json(['message' => '没有权限执行此操作'], 403);
    }
    
    // 继续处理...
}
```

### 使用辅助方法

```php
// 设置当前权限团队ID
PermissionHelper::setCurrentTeamId($teamId);

// 获取当前权限团队ID
$currentTeamId = PermissionHelper::getCurrentTeamId();

// 刷新用户权限缓存
PermissionHelper::refreshUserPermissionCache($user);

// 检查是否为超级管理员
if (PermissionHelper::isSuperAdmin()) {
    // 超级管理员可执行的操作...
}
```

### 视图中使用

```php
@can('update_orders')
    <button>编辑订单</button>
@endcan

@role('owner')
    <button>删除订单</button>
@endrole
```

### API中使用

```php
// 在API控制器中
public function index(Request $request)
{
    // 确保使用当前团队ID
    $teamId = $request->user()->currentTeam->id;
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($teamId);
    
    // 刷新用户关联以获取最新权限
    $request->user()->unsetRelation('roles')->unsetRelation('permissions');
    
    if ($request->user()->can('view_orders')) {
        return OrderResource::collection(Order::all());
    }
    
    return response()->json(['message' => '权限不足'], 403);
}
```

## 6. 团队和权限管理

### 创建新团队

```php
$team = \Laravel\Jetstream\Jetstream::newTeamModel();
$team->name = '销售团队';
$team->user_id = $ownerId; // 团队所有者ID
$team->personal_team = false;
$team->save();
```

### 添加用户到团队

```php
$user->teams()->attach($team);
```

### 创建角色和权限

```php
// 设置团队ID
app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($teamId);

// 创建角色
$role = \Spatie\Permission\Models\Role::create([
    'name' => 'sales_manager',
    'guard_name' => 'web',
    'team_id' => $teamId
]);

// 创建权限
$permission = \Spatie\Permission\Models\Permission::create([
    'name' => 'manage_sales',
    'guard_name' => 'web',
    'team_id' => $teamId
]);

// 分配权限给角色
$role->givePermissionTo($permission);

// 分配角色给用户
$user->assignRole($role);
```

### 切换团队

当用户切换团队时，权限上下文会自动更新（通过`TeamSwitched`事件监听）：

```php
$user->switchTeam($team);
```

## 7. 最佳实践

1. **使用常量**：使用`TeamConstants`类中的常量而非硬编码团队ID
2. **权限粒度**：设计合适粒度的权限，避免过细或过粗
3. **团队ID传递**：总是确保在权限检查前正确设置团队ID
4. **错误处理**：提供友好的权限错误消息
5. **日志记录**：记录关键权限变更操作，特别是删除和提权操作
6. **缓存管理**：使用`refreshUserPermissionCache`清除缓存以避免权限更改后的不一致

## 8. 故障排除

### 权限检查失败

检查以下几点：

1. 用户是否在正确的团队中
2. 团队ID是否正确设置
3. 权限名称是否拼写正确
4. 用户角色是否正确分配

### 权限不一致

如果权限变更后出现不一致，可能需要刷新缓存：

```php
// 清除单个用户的权限缓存
PermissionHelper::refreshUserPermissionCache($user);

// 清除所有权限缓存
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
```

## 9. 测试

系统提供了测试命令用于验证团队权限集成：

```bash
# 测试权限系统配置
php artisan test:team-permission

# 检查特定用户的权限
php artisan test:team-permission check-user {用户ID}

# 创建测试数据
php artisan test:team-permission create-test-data

# 测试团队切换
php artisan test:team-permission team-switch-test {用户ID}
```

## 附录：常用权限列表

| 权限名 | 说明 |
|--------|------|
| view_orders | 查看订单 |
| create_orders | 创建订单 |
| update_orders | 更新订单 |
| delete_orders | 删除订单 |
| view_users | 查看用户 |
| manage_users | 管理用户 |
| view_reports | 查看报表 |
| export_reports | 导出报表 |
| manage_system | 系统设置 | 