<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'google_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const USERNAME_MIN = 3;

    public const USERNAME_MAX = 30;

    public const USERNAME_PATTERN = '/^[A-Za-z0-9_-]+$/';

    /**
     * @return array<int, string>
     */
    public static function usernameRules(): array
    {
        return [
            'required',
            'string',
            'min:'.self::USERNAME_MIN,
            'max:'.self::USERNAME_MAX,
            'regex:'.self::USERNAME_PATTERN,
        ];
    }

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
