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

    /**
     * What a username may be.
     *
     * It was 255 characters of anything, and that name prints on every gallery
     * card and every leaderboard row — one long one stretched the layout for
     * everybody looking at it.
     *
     * These live here rather than in the form request because Google sign-in
     * does not go through one: it builds a name from the provider and writes it
     * straight to the column, so the sanitiser there has to agree with the rule
     * here, and agreeing means sharing the numbers.
     */
    public const USERNAME_MIN = 3;

    public const USERNAME_MAX = 30;

    /** Letters, digits, hyphen and underscore. */
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
     * Get the attributes that should be cast.
     *
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
