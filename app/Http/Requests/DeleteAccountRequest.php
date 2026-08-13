<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Confirmed by typing your username, not your password: accounts created
     * through Google never set one, and asking for it would leave those people
     * unable to delete their own account.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', Rule::in([$this->user()->username])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.in' => 'Type your username exactly as it is to delete your account.',
        ];
    }
}
