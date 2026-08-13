<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishDrawingRequest extends FormRequest
{
    /**
     * The route sits in the auth group and the controller checks ownership.
     */
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
            // The title is what the gallery lists a level by, so publishing
            // without one would put a nameless card in front of everybody.
            'title' => ['required', 'string', 'max:80'],

            // Optional: a level can speak for itself. Capped because the whole
            // description sits on a card, not on a page of its own.
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
