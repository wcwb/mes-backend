<?php

namespace App\Console\Commands;

use App\Helpers\SoftDeleteHelper;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestSoftDelete extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'test:soft-delete {action=test} {id?}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '测试级联软删除功能';

    /**
     * 执行命令
     */
    public function handle()
    {
        $action = $this->argument('action');
        $id = $this->argument('id');

        switch ($action) {
            case 'test':
                $this->testSoftDelete();
                break;
            case 'delete-user':
                $this->deleteUser($id);
                break;
            case 'delete-team':
                $this->deleteTeam($id);
                break;
            case 'restore-user':
                $this->restoreUser($id);
                break;
            case 'restore-team':
                $this->restoreTeam($id);
                break;
            case 'force-delete-user':
                $this->forceDeleteUser($id);
                break;
            case 'force-delete-team':
                $this->forceDeleteTeam($id);
                break;
            case 'verify':
                $this->verifyTrashed();
                break;
            default:
                $this->error('未知操作: ' . $action);
                break;
        }
    }

    /**
     * 执行软删除测试
     */
    private function testSoftDelete()
    {
        $this->info('执行软删除测试...');
        
        // 创建测试用户
        $user = User::firstOrCreate(
            ['email' => 'test_soft_delete@example.com'],
            [
                'name' => '软删除测试用户',
                'password' => bcrypt('password'),
            ]
        );
        
        // 创建测试团队
        $team = $user->ownedTeams()->firstOrCreate(
            ['name' => '软删除测试团队'],
            ['personal_team' => false]
        );
        
        // 创建测试邀请
        $invitation = $team->teamInvitations()->firstOrCreate([
            'email' => 'test_invite@example.com',
            'role' => 'editor',
        ]);
        
        $this->info("已创建测试数据:");
        $this->info("- 用户ID: {$user->id}, 名称: {$user->name}");
        $this->info("- 团队ID: {$team->id}, 名称: {$team->name}");
        $this->info("- 邀请ID: {$invitation->id}, 邮箱: {$invitation->email}");
        
        $this->info("\n测试命令:");
        $this->info("- 软删除用户: php artisan test:soft-delete delete-user {$user->id}");
        $this->info("- 软删除团队: php artisan test:soft-delete delete-team {$team->id}");
        $this->info("- 恢复用户: php artisan test:soft-delete restore-user {$user->id}");
        $this->info("- 恢复团队: php artisan test:soft-delete restore-team {$team->id}");
        $this->info("- 强制删除用户: php artisan test:soft-delete force-delete-user {$user->id}");
        $this->info("- 强制删除团队: php artisan test:soft-delete force-delete-team {$team->id}");
        $this->info("- 验证软删除状态: php artisan test:soft-delete verify");
    }
    
    /**
     * 软删除用户
     */
    private function deleteUser($userId)
    {
        if (!$userId) {
            $this->error('需要提供用户ID');
            return;
        }
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("未找到ID为 {$userId} 的用户");
            return;
        }
        
        $this->info("正在软删除用户: {$user->name} (ID: {$user->id})");
        SoftDeleteHelper::softDelete($user);
        $this->info("用户已软删除，相关联的数据也应被级联软删除");
    }
    
    /**
     * 软删除团队
     */
    private function deleteTeam($teamId)
    {
        if (!$teamId) {
            $this->error('需要提供团队ID');
            return;
        }
        
        $team = Team::find($teamId);
        if (!$team) {
            $this->error("未找到ID为 {$teamId} 的团队");
            return;
        }
        
        $this->info("正在软删除团队: {$team->name} (ID: {$team->id})");
        SoftDeleteHelper::softDelete($team);
        $this->info("团队已软删除，相关联的数据也应被级联软删除");
    }
    
    /**
     * 恢复软删除的用户
     */
    private function restoreUser($userId)
    {
        if (!$userId) {
            $this->error('需要提供用户ID');
            return;
        }
        
        $user = User::withTrashed()->find($userId);
        if (!$user) {
            $this->error("未找到ID为 {$userId} 的用户");
            return;
        }
        
        if (!$user->trashed()) {
            $this->warn("用户 {$user->name} 未被软删除");
            return;
        }
        
        $this->info("正在恢复用户: {$user->name} (ID: {$user->id})");
        SoftDeleteHelper::restore($user);
        $this->info("用户已恢复，相关联的数据也应被级联恢复");
    }
    
    /**
     * 恢复软删除的团队
     */
    private function restoreTeam($teamId)
    {
        if (!$teamId) {
            $this->error('需要提供团队ID');
            return;
        }
        
        $team = Team::withTrashed()->find($teamId);
        if (!$team) {
            $this->error("未找到ID为 {$teamId} 的团队");
            return;
        }
        
        if (!$team->trashed()) {
            $this->warn("团队 {$team->name} 未被软删除");
            return;
        }
        
        $this->info("正在恢复团队: {$team->name} (ID: {$team->id})");
        SoftDeleteHelper::restore($team);
        $this->info("团队已恢复，相关联的数据也应被级联恢复");
    }
    
    /**
     * 强制删除用户
     */
    private function forceDeleteUser($userId)
    {
        if (!$userId) {
            $this->error('需要提供用户ID');
            return;
        }
        
        $user = User::withTrashed()->find($userId);
        if (!$user) {
            $this->error("未找到ID为 {$userId} 的用户");
            return;
        }
        
        if ($this->confirm("确定要永久删除用户 {$user->name} 吗？此操作不可恢复！", false)) {
            $this->info("正在永久删除用户: {$user->name} (ID: {$user->id})");
            SoftDeleteHelper::forceDelete($user);
            $this->info("用户已永久删除，相关联的数据也应被级联永久删除");
        } else {
            $this->info("已取消强制删除操作");
        }
    }
    
    /**
     * 强制删除团队
     */
    private function forceDeleteTeam($teamId)
    {
        if (!$teamId) {
            $this->error('需要提供团队ID');
            return;
        }
        
        $team = Team::withTrashed()->find($teamId);
        if (!$team) {
            $this->error("未找到ID为 {$teamId} 的团队");
            return;
        }
        
        if ($this->confirm("确定要永久删除团队 {$team->name} 吗？此操作不可恢复！", false)) {
            $this->info("正在永久删除团队: {$team->name} (ID: {$team->id})");
            SoftDeleteHelper::forceDelete($team);
            $this->info("团队已永久删除，相关联的数据也应被级联永久删除");
        } else {
            $this->info("已取消强制删除操作");
        }
    }
    
    /**
     * 验证软删除状态
     */
    private function verifyTrashed()
    {
        $this->info('软删除状态验证:');
        
        // 用户软删除状态
        $this->info("\n已软删除的用户:");
        $trashedUsers = User::onlyTrashed()->get();
        if ($trashedUsers->isEmpty()) {
            $this->line("  - 无");
        } else {
            foreach ($trashedUsers as $user) {
                $this->line("  - ID: {$user->id}, 名称: {$user->name}, 邮箱: {$user->email}");
            }
        }
        
        // 团队软删除状态
        $this->info("\n已软删除的团队:");
        $trashedTeams = Team::onlyTrashed()->get();
        if ($trashedTeams->isEmpty()) {
            $this->line("  - 无");
        } else {
            foreach ($trashedTeams as $team) {
                $this->line("  - ID: {$team->id}, 名称: {$team->name}, 拥有者ID: {$team->user_id}");
            }
        }
        
        // 团队成员关系软删除状态
        $this->info("\n已软删除的团队成员关系:");
        $trashedTeamUsers = DB::table('team_user')
            ->whereNotNull('deleted_at')
            ->get();
        if ($trashedTeamUsers->isEmpty()) {
            $this->line("  - 无");
        } else {
            foreach ($trashedTeamUsers as $relation) {
                $this->line("  - 团队ID: {$relation->team_id}, 用户ID: {$relation->user_id}, 角色: {$relation->role}");
            }
        }
        
        // 团队邀请软删除状态
        $this->info("\n已软删除的团队邀请:");
        $trashedInvitations = DB::table('team_invitations')
            ->whereNotNull('deleted_at')
            ->get();
        if ($trashedInvitations->isEmpty()) {
            $this->line("  - 无");
        } else {
            foreach ($trashedInvitations as $invitation) {
                $this->line("  - ID: {$invitation->id}, 团队ID: {$invitation->team_id}, 邮箱: {$invitation->email}");
            }
        }
    }
} 