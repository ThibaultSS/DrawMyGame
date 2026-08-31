<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishDrawingRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:80'],

            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
