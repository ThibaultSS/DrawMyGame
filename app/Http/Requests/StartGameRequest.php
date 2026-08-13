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
     * Three colours make a level: something to stand on, somewhere to get to,
     * and someone to move. The old form let an empty submit through and the
     * game silently broke on the first missing hex value.
     *
     * Hazards are optional — a level with nothing dangerous in it is still a
     * level, and demanding one meant inventing a danger to get past this page.
     *
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
