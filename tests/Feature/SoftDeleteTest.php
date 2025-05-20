<?php

namespace Tests\Feature;

use App\Helpers\SoftDeleteHelper;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * 测试用户和队伍
     */
    protected User $user;
    protected Team $team;
    
    /**
     * 设置测试环境
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建测试用户和团队
        $this->createTestData();
    }
    
    /**
     * 创建测试数据
     */
    protected function createTestData(): void
    {
        // 创建测试用户
        $this->user = User::factory()->create([
            'name' => '软删除测试用户',
            'email' => 'softdelete@test.com',
        ]);
        
        // 创建测试团队
        $this->team = $this->user->ownedTeams()->create([
            'name' => '软删除测试团队',
            'personal_team' => false,
        ]);
        
        // 添加另一个成员到团队
        $member = User::factory()->create([
            'name' => '团队成员',
            'email' => 'member@test.com',
        ]);
        
        $this->team->users()->attach($member, ['role' => 'viewer']);
    }
    
    /**
     * 测试软删除用户
     */
    public function test_soft_delete_user()
    {
        // 获取测试数据初始状态
        $userId = $this->user->id;
        
        // 执行软删除
        $result = SoftDeleteHelper::softDelete($this->user);
        $this->assertTrue($result);
        
        // 验证用户已被软删除
        $this->assertSoftDeleted('users', ['id' => $userId]);
    }
    
    /**
     * 测试软删除团队
     */
    public function test_soft_delete_team()
    {
        // 获取测试数据初始状态
        $teamId = $this->team->id;
        
        // 执行软删除
        $result = SoftDeleteHelper::softDelete($this->team);
        $this->assertTrue($result);
        
        // 验证团队已被软删除
        $this->assertSoftDeleted('teams', ['id' => $teamId]);
        
        // 验证用户本身不会被删除
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'deleted_at' => null
        ]);
    }
    
    /**
     * 测试在不支持SoftDelete的模型上调用相关方法时的异常处理
     */
    public function test_soft_delete_on_unsupported_model()
    {
        // 创建不支持软删除的模型实例（模拟）
        $mockModel = $this->getMockBuilder(\Illuminate\Database\Eloquent\Model::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // 设置ID属性以便日志记录
        $mockModel->id = 999;
        
        // 执行软删除（应返回false，不会实际删除）
        $result = SoftDeleteHelper::softDelete($mockModel);
        $this->assertFalse($result);
    }
} 