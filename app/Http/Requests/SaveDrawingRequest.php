<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class SaveDrawingRequest extends FormRequest
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
            'levelImage' => ['required_without:drawingId', 'nullable', File::image()->max(10 * 1024)],
            'drawingId' => ['nullable', 'integer'],

            'speed' => ['required', 'integer', 'between:1,20'],
            'jumpHeight' => ['required', 'integer', 'between:5,30'],
        ];
    }
}
