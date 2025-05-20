<?php

namespace App\Helpers;

/**
 * 团队相关常量定义
 */
class TeamConstants
{
    /**
     * 管理员团队ID
     * 
     * 用于特权操作和超级管理员权限
     */
    public const ADMIN_TEAM_ID = 1;
    
    /**
     * 默认团队ID
     * 
     * 新用户默认被添加到此团队
     */
    public const DEFAULT_TEAM_ID = 2;
    
    /**
     * 管理员团队名称
     */
    public const ADMIN_TEAM_NAME = 'admin';
    
    /**
     * 默认团队名称
     */
    public const DEFAULT_TEAM_NAME = 'default';
    
    /**
     * 超级管理员角色名称
     */
    public const SUPER_ADMIN_ROLE = 'super_admin';
    
    /**
     * 获取团队ID对应的名称
     * 
     * @param int $teamId
     * @return string|null
     */
    public static function getTeamName(int $teamId): ?string
    {
        $teams = [
            self::ADMIN_TEAM_ID => self::ADMIN_TEAM_NAME,
            self::DEFAULT_TEAM_ID => self::DEFAULT_TEAM_NAME
        ];
        
        return $teams[$teamId] ?? null;
    }
    
    /**
     * 根据团队名称获取团队ID
     * 
     * @param string $teamName
     * @return int|null
     */
    public static function getTeamId(string $teamName): ?int
    {
        $teams = [
            self::ADMIN_TEAM_NAME => self::ADMIN_TEAM_ID,
            self::DEFAULT_TEAM_NAME => self::DEFAULT_TEAM_ID
        ];
        
        return $teams[$teamName] ?? null;
    }
    
    /**
     * 检查团队ID是否为特殊团队
     * 
     * @param int $teamId
     * @return bool
     */
    public static function isSpecialTeam(int $teamId): bool
    {
        return in_array($teamId, [self::ADMIN_TEAM_ID, self::DEFAULT_TEAM_ID]);
    }
} 