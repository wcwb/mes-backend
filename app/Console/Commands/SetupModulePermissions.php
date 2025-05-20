<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupModulePermissions extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'permissions:setup-modules';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '设置模块权限并分配给相应角色';

    /**
     * 执行命令
     */
    public function handle()
    {
        $this->info('开始设置模块权限...');
        
        // 运行模块权限种子
        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\ModulePermissionSeeder',
        ]);
        
        $this->info('模块权限设置完成！');
        
        return Command::SUCCESS;
    }
} 