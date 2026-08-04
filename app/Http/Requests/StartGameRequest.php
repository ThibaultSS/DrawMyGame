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
     * The engine cannot build a level without all four colours: the old form
     * let an empty submit through and the game silently broke on the first
     * missing hex value.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'platformColor' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'goalColor' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'playerColor' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'hazardColor' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
        ];
    }
}
