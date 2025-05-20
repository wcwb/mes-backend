<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupSuperAdmin extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'admin:create-super';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '创建系统超级管理员';

    /**
     * 执行命令
     */
    public function handle()
    {
        $this->info('开始创建超级管理员...');
        
        // 运行模块权限种子（确保先有权限）
        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\ModulePermissionSeeder',
        ]);
        
        // 运行超级管理员种子
        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\SuperAdminSeeder',
        ]);
        
        $this->info('超级管理员设置完成！');
        
        return Command::SUCCESS;
    }
} 