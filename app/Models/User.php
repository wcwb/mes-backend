<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasTeams, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string> 
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_team_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
     * 获取用户所有团队（包括拥有的和加入的）
     * 排除已软删除的团队和成员关系
     * 
     * @return \Illuminate\Support\Collection
     */
    public function allTeams()
    {
        return $this->ownedTeams->merge($this->teams)->sortBy('name');
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
}
