<?php

return [
    'groups' => [
        'api' => [
            'teams.index',
            'teams.store',
            'teams.show',
            'teams.update',
            'teams.destroy',
            'api.team-members.index', // 团队成员列表
            'api.team-members.store', // 添加团队成员
            'api.team-members.update', // 更新团队成员角色
            'api.team-members.destroy', // 移除团队成员
        ],
    ],
    
    // 路由名称别名映射
    'aliases' => [
        // 团队相关路由别名
        'api.team-members.index' => 'api.teams.{teamId}.members.index',
        'api.team-members.store' => 'api.teams.{teamId}.members.store',
        'api.team-members.update' => 'api.teams.{teamId}.members.update',
        'api.team-members.destroy' => 'api.teams.{teamId}.members.destroy',
    ],
]; 