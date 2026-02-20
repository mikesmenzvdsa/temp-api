<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class FrontendUser extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'surname',
        'login',
        'username',
        'email',
        'password',
        'activation_code',
        'reset_password_code',
        'permissions',
        'is_activated',
        'activated_at',
        'last_login',
        'is_guest',
        'is_superuser',
        'last_seen',
        'created_ip_address',
        'last_ip_address',
    ];

    protected $hidden = [
        'password',
        'activation_code',
        'reset_password_code',
        'persist_code',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_activated' => 'boolean',
        'is_guest' => 'boolean',
        'is_superuser' => 'boolean',
        'activated_at' => 'datetime',
        'last_login' => 'datetime',
        'last_seen' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $rememberTokenName = 'persist_code';
}
