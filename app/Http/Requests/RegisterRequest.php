<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The rules live on the model: Google sign-in writes a username
            // without going through this request, and its sanitiser has to
            // agree with what is allowed here.
            'username' => [...User::usernameRules(), 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            // Password::defaults() is at least 8 characters. Before this, 'a'
            // was a valid password.
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
