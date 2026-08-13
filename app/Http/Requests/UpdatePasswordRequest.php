<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The current password is asked for so that a borrowed, still-signed-in
     * browser cannot be used to lock the owner out of their own account.
     *
     * Accounts created through Google were given a random password nobody
     * knows, so this form is not usable by them — signing in there happens
     * through Google, and there is nothing for a password to unlock.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
