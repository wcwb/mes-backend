# HasTeams Trait 使用指南

## 概述

`HasTeams` trait 是 Laravel Jetstream 提供的一个重要特性，用于为用户模型添加团队管理功能。该 trait 提供了完整的团队关系管理、权限控制和角色分配功能。

## 主要功能

- 用户与团队的关联管理
- 当前团队上下文切换
- 团队角色和权限管理
- 团队所有权检查

## 核心方法详解

### 1. 团队关系管理

#### `currentTeam()`
获取用户当前团队的关联关系。

```php
// 获取用户当前团队
$currentTeam = $user->currentTeam;

// 检查用户是否有当前团队
if ($user->currentTeam) {
    echo "当前团队: " . $user->currentTeam->name;
}
```

#### `allTeams()`
获取用户拥有或所属的所有团队。

```php
// 获取用户的所有团队（包括拥有的和参与的）
$allTeams = $user->allTeams();

foreach ($allTeams as $team) {
    echo "团队: " . $team->name . "\n";
}
```

#### `ownedTeams()`
获取用户拥有的团队关联关系。

```php
// 获取用户拥有的团队
$ownedTeams = $user->ownedTeams;

foreach ($ownedTeams as $team) {
    echo "拥有的团队: " . $team->name . "\n";
}
```

#### `teams()`
获取用户所属的团队关联关系（不包括拥有的团队）。

```php
// 获取用户参与的团队
$memberTeams = $user->teams;

foreach ($memberTeams as $team) {
    echo "参与的团队: " . $team->name;
    echo "角色: " . $team->membership->role . "\n";
}
```

#### `personalTeam()`
获取用户的个人团队。

```php
// 获取用户的个人团队
$personalTeam = $user->personalTeam();

if ($personalTeam) {
    echo "个人团队: " . $personalTeam->name;
}
```

### 2. 团队状态检查

#### `isCurrentTeam($team)`
检查给定团队是否为用户的当前团队。

```php
// 检查是否为当前团队
if ($user->isCurrentTeam($team)) {
    echo "这是用户的当前团队";
}
```

#### `ownsTeam($team)`
检查用户是否拥有指定团队。

```php
// 检查用户是否拥有团队
if ($user->ownsTeam($team)) {
    echo "用户拥有这个团队";
} else {
    echo "用户不拥有这个团队";
}
```

#### `belongsToTeam($team)`
检查用户是否属于指定团队（包括拥有或参与）。

```php
// 检查用户是否属于团队
if ($user->belongsToTeam($team)) {
    echo "用户属于这个团队";
} else {
    echo "用户不属于这个团队";
}
```

### 3. 团队切换

#### `switchTeam($team)`
切换用户的当前团队上下文。

```php
// 切换到指定团队
$success = $user->switchTeam($team);

if ($success) {
    echo "成功切换到团队: " . $team->name;
} else {
    echo "切换失败，用户不属于该团队";
}

// 在控制器中的使用示例
public function switchTeam(Request $request)
{
    $team = Team::findOrFail($request->team_id);
    
    if ($request->user()->switchTeam($team)) {
        return response()->json(['message' => '团队切换成功']);
    }
    
    return response()->json(['message' => '团队切换失败'], 403);
}
```

### 4. 角色和权限管理

#### `teamRole($team)`
获取用户在指定团队中的角色。

```php
// 获取用户在团队中的角色
$role = $user->teamRole($team);

if ($role) {
    echo "用户角色: " . $role->name;
    echo "角色权限: " . implode(', ', $role->permissions);
}
```

#### `hasTeamRole($team, $role)`
检查用户在指定团队中是否有特定角色。

```php
// 检查用户是否有特定角色
if ($user->hasTeamRole($team, 'admin')) {
    echo "用户在该团队中是管理员";
}

if ($user->hasTeamRole($team, 'editor')) {
    echo "用户在该团队中是编辑者";
}
```

#### `teamPermissions($team)`
获取用户在指定团队中的所有权限。

```php
// 获取用户在团队中的权限
$permissions = $user->teamPermissions($team);

if (in_array('*', $permissions)) {
    echo "用户拥有所有权限";
} else {
    echo "用户权限: " . implode(', ', $permissions);
}
```

#### `hasTeamPermission($team, $permission)`
检查用户在指定团队中是否有特定权限。

```php
// 检查特定权限
if ($user->hasTeamPermission($team, 'create:posts')) {
    echo "用户可以创建文章";
}

if ($user->hasTeamPermission($team, 'delete:users')) {
    echo "用户可以删除用户";
}
```

## 实际应用示例

### 1. 团队切换功能

```php
class TeamSwitchController extends Controller
{
    public function switch(Request $request)
    {
        $request->validate([
            'team_id' => 'required|exists:teams,id'
        ]);
        
        $team = Team::findOrFail($request->team_id);
        $user = $request->user();
        
        // 检查用户是否属于该团队
        if (!$user->belongsToTeam($team)) {
            return response()->json([
                'message' => '您不属于该团队'
            ], 403);
        }
        
        // 切换团队
        if ($user->switchTeam($team)) {
            return response()->json([
                'message' => '团队切换成功',
                'current_team' => $team->name
            ]);
        }
        
        return response()->json([
            'message' => '团队切换失败'
        ], 500);
    }
}
```

### 2. 权限检查中间件

```php
class CheckTeamPermission
{
    public function handle($request, Closure $next, $permission)
    {
        $user = $request->user();
        $team = $user->currentTeam;
        
        if (!$team) {
            return response()->json(['message' => '未设置当前团队'], 403);
        }
        
        if (!$user->hasTeamPermission($team, $permission)) {
            return response()->json([
                'message' => '权限不足'
            ], 403);
        }
        
        return $next($request);
    }
}
```

### 3. 团队管理界面

```php
class TeamController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'current_team' => $user->currentTeam,
            'owned_teams' => $user->ownedTeams,
            'member_teams' => $user->teams,
            'all_teams' => $user->allTeams()
        ]);
    }
    
    public function show(Request $request, Team $team)
    {
        $user = $request->user();
        
        if (!$user->belongsToTeam($team)) {
            return response()->json(['message' => '无权访问'], 403);
        }
        
        return response()->json([
            'team' => $team,
            'user_role' => $user->teamRole($team),
            'user_permissions' => $user->teamPermissions($team),
            'is_owner' => $user->ownsTeam($team)
        ]);
    }
}
```

## 注意事项

1. **团队上下文**: 在使用权限相关方法时，确保用户有正确的当前团队设置。

2. **权限检查**: 使用 `hasTeamPermission` 时，如果用户拥有团队，会自动获得所有权限。

3. **API Token**: 如果使用 API Token，权限检查会同时考虑 Token 的权限范围。

4. **性能考虑**: 频繁的团队切换可能影响性能，建议在必要时才进行切换。

5. **数据一致性**: 切换团队后，相关的权限和角色信息会自动更新。

## 最佳实践

1. **初始化检查**: 在应用启动时检查用户是否有当前团队，如果没有则设置默认团队。

2. **权限缓存**: 对于频繁的权限检查，考虑使用缓存机制。

3. **团队验证**: 在执行团队相关操作前，始终验证用户的团队归属。

4. **错误处理**: 为团队切换和权限检查提供适当的错误处理机制。

5. **日志记录**: 记录重要的团队操作，如切换、权限变更等。 