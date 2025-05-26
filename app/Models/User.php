<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Gate;
use App\Models\Trait\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasTeams, Notifiable, SoftDeletes;

    // 使用 guarded 而不是 fillable 来保护敏感数据
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'current_team_id' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * 在模型启动时注册超级管理员权限门面
     * 这个方法会在模型初始化时自动调用
     */
    protected static function booted()
    {
        static::registerSuperAdminGate();
    }

    /**
     * 注册超级管理员权限门面
     * 为超级管理员用户自动授予所有权限
     * 
     * @return void
     */
    protected static function registerSuperAdminGate()
    {
        Gate::before(function (User $user) {
            // 如果用户是超级管理员，直接返回true授予所有权限
            // 否则返回null，让其他权限检查继续执行
            return $user->is_super_admin ? true : null;
        });
    }

    /**
     * 获取用户所有团队（包括已软删除的）
     * 
     * @param bool $withTrashed 是否包含已软删除的团队和成员关系
     * @return \Illuminate\Support\Collection
     */
    public function allTeamsWithTrashed($withTrashed = true)
    {
        $ownedTeams = $withTrashed
            ? $this->ownedTeams()->withTrashed()->get()
            : $this->ownedTeams;

        $memberTeams = $this->belongsToMany(Team::class, 'team_user')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps()
            ->as('membership');

        if ($withTrashed) {
            $memberTeams = $memberTeams->withTrashed()->get();
        } else {
            $memberTeams = $memberTeams->get();
        }

        return $ownedTeams->merge($memberTeams)->sortBy('name');
    }

    /**
     * 重写belongsToTeams方法，使用自定义的Membership模型支持软删除
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps()
            ->whereNull('team_user.deleted_at')
            ->as('membership');
    }

    /**
     * 设置用户在线状态
     * 
     * @param bool $status 在线状态
     * @return bool
     */
    public function online($status = true)
    {
        return $this->update([
            'online' => $status,
            'last_login_at' => $status ? now() : null,
            'last_login_ip' => $status ? request()->ip() : null,
        ]);
    }
}
