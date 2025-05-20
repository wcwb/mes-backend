<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\PermissionRegistrar;

class UserResource extends JsonResource
{
    /**
     * 将资源转换为数组
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        // 预加载所有必要的关系
        if (!$this->relationLoaded('currentTeam') || !$this->relationLoaded('ownedTeams') || !$this->relationLoaded('teams')) {
            $this->load(['currentTeam', 'ownedTeams', 'teams']);
        }
        
        // 重新加载关联关系，确保数据是最新的
        if ($this->relationLoaded('roles') === false || $this->relationLoaded('permissions') === false) {
            $this->load(['roles', 'permissions']);
        }
        
        // 设置权限系统团队上下文为用户当前团队
        $teamId = $this->current_team_id ?? ($this->currentTeam ? $this->currentTeam->id : 2);
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        
        // 获取用户所有权限（包括通过角色获得的权限）
        $permissions = $this->getPermissionsViaRoles()->merge($this->getDirectPermissions())->pluck('name')->unique();
        
        // 获取用户角色
        $roles = $this->roles->pluck('name');
        
        // 构建用户基础数据
        $userData = [
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'work_no' => $this->work_no,
            'phone' => $this->phone,
            'position' => $this->position,
            'avatar_url' => $this->avatar_url,
            'commencement_date' => $this->commencement_date,
            'last_login_at' => $this->last_login_at,
            'abbreviation' => $this->abbreviation,
            'last_login_ip' => $this->last_login_ip,
            'lang' => $this->lang,
            'timezone' => $this->timezone,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'is_super_admin' => $this->is_super_admin,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'current_team_id' => $this->current_team_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // 将角色和权限添加到用户数据中
            'roles' => $roles,
            'permissions' => $permissions,
        ];
        
        // 如果包含当前团队
        if ($this->whenLoaded('currentTeam')) {
            $userData['current_team'] = $this->currentTeam;
        }
        
        // 如果包含拥有的团队
        if ($this->whenLoaded('ownedTeams')) {
            $userData['owned_teams'] = $this->ownedTeams;
        }
        
        // 如果包含所属团队
        if ($this->whenLoaded('teams')) {
            $userData['teams'] = $this->teams;
        }
        
        return $userData;
    }
} 