<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsernameRequest extends FormRequest
{
    /**
     * The route sits in the auth group, and you can only change your own.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same rules registration applies, except that your own name is not a
     * collision with itself — without ignore(), saving the form unchanged would
     * be refused as taken.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($this->user()->id),
            ],
        ];
    }
}
