<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membership extends Pivot
{
    use SoftDeletes;
    
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'team_user';

    protected $hidden = [
        'created_at',
        'updated_at'
    ];
    
    /**
     * 需要类型转换的属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deleted_at' => 'datetime',
    ];
} 