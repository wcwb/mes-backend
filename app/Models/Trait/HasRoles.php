<?php

namespace App\Models\Trait;

use Spatie\Permission\Traits\HasRoles as TraitsHasRoles;

trait HasRoles
{
    use TraitsHasRoles;

    /**
     * 检查用户是否拥有指定权限（支持多团队环境）
     * 
     * 这个方法解决了Spatie Permission包在多团队环境下使用权限名称字符串检查的问题。
     * 它会在当前团队上下文中查找权限，而不是使用第一个匹配的权限。
     * 
     * @param string|\Spatie\Permission\Contracts\Permission $permission 权限名称或权限对象
     * @param string|null $guardName 守卫名称，默认为null
     * @return bool
     */
    public function hasPermissionToSafely($permission, $guardName = null): bool
    {
        // 如果传入的是权限对象，直接使用原生方法
        if (is_object($permission)) {
            return $this->hasPermissionTo($permission, $guardName);
        }

        // 如果传入的是权限名称字符串，需要在当前团队上下文中查找
        $permissionName = (string) $permission;
        $guardName = $guardName ?? $this->getDefaultGuardName();

        // 获取当前团队ID
        $currentTeamId = $this->getCurrentTeamId();

        if ($currentTeamId === null) {
            // 如果没有当前团队上下文，使用原生方法（可能会有问题，但保持兼容性）
            return $this->hasPermissionTo($permissionName, $guardName);
        }

        // 使用缓存键来避免重复查询
        $cacheKey = "permission_object_{$permissionName}_{$guardName}_{$currentTeamId}";

        // 尝试从请求级别的缓存中获取权限对象
        static $permissionCache = [];

        if (!isset($permissionCache[$cacheKey])) {
            // 在当前团队上下文中查找权限对象
            $permissionCache[$cacheKey] = \Spatie\Permission\Models\Permission::where('name', $permissionName)
                ->where('guard_name', $guardName)
                ->where('team_id', $currentTeamId)
                ->first();
        }

        $permissionObject = $permissionCache[$cacheKey];

        if (!$permissionObject) {
            // 权限不存在
            return false;
        }

        // 使用权限对象进行检查
        return $this->hasPermissionTo($permissionObject, $guardName);
    }

    /**
     * 检查用户是否可以执行指定操作（支持多团队环境）
     * 
     * 这是Laravel Gate系统的can方法的安全版本，支持多团队环境。
     * 
     * @param string $ability 能力/权限名称
     * @param array|mixed $arguments 额外参数
     * @return bool
     */
    public function canSafely($ability, $arguments = []): bool
    {
        // 首先尝试使用我们的安全权限检查方法
        if ($this->hasPermissionToSafely($ability)) {
            return true;
        }

        // 如果权限检查失败，回退到Laravel原生的Gate检查
        // 这样可以支持其他类型的授权逻辑（如Policy等）
        return $this->can($ability, $arguments);
    }

    /**
     * 获取当前团队ID
     * 
     * 优先级：
     * 1. Spatie Permission注册器中设置的团队ID（临时切换的团队）
     * 2. 用户的当前团队ID（默认团队）
     * 3. null（没有团队上下文）
     * 
     * @return int|null
     */
    private function getCurrentTeamId(): ?int
    {
        // 首先尝试从Spatie Permission注册器获取当前团队ID（临时切换的团队）
        $permissionRegistrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $spatieTeamId = $permissionRegistrar->getPermissionsTeamId();

        if ($spatieTeamId !== null) {
            return $spatieTeamId;
        }

        // 如果Spatie没有设置团队ID，使用用户的当前团队ID作为默认
        return $this->current_team_id;
    }

    /**
     * 自动设置用户的当前团队为权限上下文
     * 
     * 这个方法会将用户的current_team_id设置为Spatie Permission的团队上下文，
     * 这样在进行权限操作时就不需要手动设置团队ID了。
     * 
     * @return $this
     */
    public function setCurrentTeamAsPermissionContext(): self
    {
        if ($this->current_team_id) {
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->current_team_id);
        }

        return $this;
    }

    /**
     * 在指定团队上下文中执行操作
     * 
     * 这个方法允许临时切换到指定团队执行操作，操作完成后自动恢复原始团队上下文。
     * 
     * @param int $teamId 临时切换到的团队ID
     * @param callable $callback 要执行的操作
     * @return mixed 回调函数的返回值
     */
    public function withTeamContext(int $teamId, callable $callback)
    {
        $permissionRegistrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $originalTeamId = $permissionRegistrar->getPermissionsTeamId();

        try {
            // 临时设置团队上下文
            $permissionRegistrar->setPermissionsTeamId($teamId);

            // 清除权限缓存，确保团队上下文切换生效
            $permissionRegistrar->forgetCachedPermissions();

            // 重新加载用户关系，确保使用新的团队上下文
            $this->load('roles');

            // 执行操作
            $result = $callback($this);

            return $result;
        } finally {
            // 恢复原始团队上下文
            $permissionRegistrar->setPermissionsTeamId($originalTeamId);

            // 清除权限缓存，确保团队上下文恢复生效
            $permissionRegistrar->forgetCachedPermissions();
        }
    }

    /**
     * 批量检查用户权限（支持多团队环境）
     * 
     * @param array $permissions 权限名称数组
     * @param bool $requireAll 是否需要拥有所有权限（true）还是任意一个权限（false）
     * @param string|null $guardName 守卫名称
     * @return bool
     */
    public function hasAnyPermissionSafely(array $permissions, bool $requireAll = false, $guardName = null): bool
    {
        if (empty($permissions)) {
            return true;
        }

        $results = [];
        foreach ($permissions as $permission) {
            $results[] = $this->hasPermissionToSafely($permission, $guardName);
        }

        if ($requireAll) {
            // 需要拥有所有权限
            return !in_array(false, $results, true);
        } else {
            // 只需要拥有任意一个权限
            return in_array(true, $results, true);
        }
    }

    /**
     * 在指定团队上下文中检查权限
     * 
     * @param int $teamId 团队ID
     * @param string|\Spatie\Permission\Contracts\Permission $permission 权限名称或权限对象
     * @param string|null $guardName 守卫名称
     * @return bool
     */
    public function hasPermissionInTeam(int $teamId, $permission, $guardName = null): bool
    {
        return $this->withTeamContext($teamId, function ($user) use ($permission, $guardName) {
            return $user->hasPermissionToSafely($permission, $guardName);
        });
    }

    /**
     * 安全地分配角色给用户（自动使用用户的当前团队）
     * 
     * 这个方法会自动设置用户的当前团队为权限上下文，然后分配角色。
     * 如果需要分配其他团队的角色，请使用 assignRoleInTeam() 方法。
     * 
     * @param mixed $roles 角色名称、角色对象或角色数组
     * @return $this
     */
    public function assignRoleSafely($roles): self
    {
        // 自动设置当前团队为权限上下文
        $this->setCurrentTeamAsPermissionContext();

        // 分配角色
        $this->assignRole($roles);

        return $this;
    }

    /**
     * 在指定团队中分配角色给用户
     * 
     * @param int $teamId 团队ID
     * @param mixed $roles 角色名称、角色对象或角色数组
     * @return $this
     */
    public function assignRoleInTeam(int $teamId, $roles): self
    {
        $this->withTeamContext($teamId, function ($user) use ($roles) {
            $user->assignRole($roles);
        });

        // 注意：不要在这里调用 $this->load('roles')，因为当前团队上下文可能已经改变
        // 角色关系会根据当前团队上下文进行过滤，所以需要在正确的团队上下文中查询

        return $this;
    }

    /**
     * 安全地移除用户角色（自动使用用户的当前团队）
     * 
     * @param mixed $roles 角色名称、角色对象或角色数组
     * @return $this
     */
    public function removeRoleSafely($roles): self
    {
        // 自动设置当前团队为权限上下文
        $this->setCurrentTeamAsPermissionContext();

        // 移除角色
        $this->removeRole($roles);

        return $this;
    }

    /**
     * 在指定团队中移除用户角色
     * 
     * @param int $teamId 团队ID
     * @param mixed $roles 角色名称、角色对象或角色数组
     * @return $this
     */
    public function removeRoleInTeam(int $teamId, $roles): self
    {
        $this->withTeamContext($teamId, function ($user) use ($roles) {
            $user->removeRole($roles);
        });

        return $this;
    }

    /**
     * 安全地同步用户角色（自动使用用户的当前团队）
     * 
     * @param mixed $roles 角色名称、角色对象或角色数组
     * @return $this
     */
    public function syncRolesSafely($roles): self
    {
        // 自动设置当前团队为权限上下文
        $this->setCurrentTeamAsPermissionContext();

        // 同步角色
        $this->syncRoles($roles);

        return $this;
    }

    /**
     * 获取用户在指定团队中的角色
     * 
     * @param int $teamId 团队ID
     * @return \Illuminate\Support\Collection
     */
    public function getRolesInTeam(int $teamId): \Illuminate\Support\Collection
    {
        // 直接查询数据库，避免团队上下文的干扰
        $roleClass = config('permission.models.role');

        return $roleClass::join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', static::class)
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('model_has_roles.team_id', $teamId)
            ->select('roles.*')
            ->get();
    }

    /**
     * 获取用户在所有团队中的角色（不受当前团队上下文限制）
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getAllRoles(): \Illuminate\Support\Collection
    {
        // 直接查询数据库，不受团队上下文限制
        $roleClass = config('permission.models.role');

        return $roleClass::join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', static::class)
            ->where('model_has_roles.model_id', $this->getKey())
            ->select('roles.*', 'model_has_roles.team_id as pivot_team_id')
            ->get();
    }
}
