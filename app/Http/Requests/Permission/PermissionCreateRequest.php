<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;

class PermissionCreateRequest extends FormRequest
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
        // 判断用户是否有当前团队，如果没有则使用默认团队ID
        $teamId = $this->user()->currentTeam ? $this->user()->currentTeam->id : 1;
        
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // 确保同一团队内权限名称唯一
                "unique:permissions,name,NULL,id,team_id,{$teamId}",
            ],
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
            'name' => '权限名称',
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
            'name.required' => '权限名称不能为空',
            'name.unique' => '该权限名称已存在',
        ];
    }
} 