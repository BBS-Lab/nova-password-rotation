<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use BBSLab\LaravelPasswordRotation\Concerns\RotatesPassword;
use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Workbench\Database\Factories\UserFactory;

class User extends Authenticatable implements MustRotatePassword
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, RotatesPassword;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
