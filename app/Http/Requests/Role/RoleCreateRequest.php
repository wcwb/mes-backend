<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class RoleCreateRequest extends FormRequest
{
    /**
     * 确定用户是否有权提交此请求
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 获取适用于请求的验证规则
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()->currentTeam->id;
        
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // 确保同一团队内角色名称唯一
                "unique:roles,name,NULL,id,team_id,{$teamId}",
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];
    }
    
    /**
     * 获取验证错误的自定义属性
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '角色名称',
            'permissions' => '权限列表',
            'permissions.*' => '权限',
        ];
    }
    
    /**
     * 获取已定义验证规则的错误消息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '角色名称不能为空',
            'name.unique' => '该角色名称已存在',
            'permissions.*.exists' => '指定的权限不存在',
        ];
    }
} 