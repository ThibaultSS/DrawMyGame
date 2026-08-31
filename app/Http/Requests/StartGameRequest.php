<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'platformColor' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'goalColor' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'playerColor' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'hazardColor' => ['nullable', 'regex:/^#[0-9a-f]{6}$/i'],
        ];
    }
}
