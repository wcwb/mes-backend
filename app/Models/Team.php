<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;
use App\Models\Scopes\TeamScope;

class Team extends JetstreamTeam
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'personal_team' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
        'description',
        'user_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * The event map for the model.
     *  
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];


    /**
     * 删除团队及其关联数据
     */
    public function purge(): void
    {
        $this->users()->detach();

        $this->teamInvitations()->delete();

        $this->delete();
    }

    /**
     * 强制删除团队及其关联数据（绕过软删除）
     */
    public function forceRemove(): void
    {
        $this->users()->detach();

        $this->teamInvitations()->get()->each(function ($invitation) {
            $invitation->forceDelete();
        });

        $this->forceDelete();
    }

    /**
     * 获取所有团队成员（包括已软删除的）
     * 
     * @param  bool  $withTrashed  是否包含已软删除的成员关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function allTeamMembers($withTrashed = false)
    {
        $query = $this->belongsToMany(User::class, 'team_user')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps()
            ->as('membership');

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * 重写users方法，使用自定义的Membership模型支持软删除
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps()
            ->whereNull('team_user.deleted_at')
            ->as('membership');
    }

    /**
     * 重写teamInvitations方法，自动排除已软删除的邀请
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function teamInvitations()
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * 获取所有团队邀请（包括已软删除的）
     * 
     * @param  bool  $withTrashed  是否包含已软删除的邀请
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function allTeamInvitations($withTrashed = false)
    {
        $query = $this->hasMany(TeamInvitation::class);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query;
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // 确保表名称正确
        $this->setTable('teams');
    }

    /**
     * 将字段重命名为数据库列名
     *
     * @return array
     */
    public function getAttributes()
    {
        $attributes = parent::getAttributes();

        // 检查数据库列名是否与属性名不同，并进行适当的转换
        // 例如，如果数据库中的列名是 team_name 而不是 name
        // 这里可以添加自定义的映射逻辑

        return $attributes;
    }


    /* protected static function booted(): void
    {
        static::addGlobalScope(new TeamScope);
    } */
}
