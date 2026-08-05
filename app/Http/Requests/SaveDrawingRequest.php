<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveDrawingRequest extends FormRequest
{
    /**
     * The route sits in the auth group; being signed in is the whole check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The slider positions saved with the drawing. The bounds are the sliders'
     * own min/max on the game page — anything outside them never came from the
     * interface.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'speed' => ['required', 'integer', 'between:1,20'],
            'jumpHeight' => ['required', 'integer', 'between:5,30'],
        ];
    }
}
