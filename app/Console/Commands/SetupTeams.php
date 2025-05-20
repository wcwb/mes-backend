<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupTeams extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'teams:setup';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '设置特殊团队：admin(ID=1)和default(ID=2)';

    /**
     * 执行命令
     */
    public function handle()
    {
        $this->info('开始设置特殊团队...');
        
        // 运行团队种子
        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\TeamsSeeder',
        ]);
        
        $this->info('特殊团队设置完成！');
        
        return Command::SUCCESS;
    }
} 