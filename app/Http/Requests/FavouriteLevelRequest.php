<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FavouriteLevelRequest extends FormRequest
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
            'speed' => ['nullable', 'integer', 'between:1,20'],
            'jumpHeight' => ['nullable', 'integer', 'between:5,30'],
        ];
    }
}
