<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

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
     * Saving is the only route that accepts a level image, because it is the
     * first moment the server has a reason to keep one: until Save is pressed
     * the browser holds the level itself.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // File::image() checks the actual file content, not just the
            // extension, and excludes SVG — which can carry scripts, so it is
            // not an image in the safe sense. The size cap fits any phone
            // photo while stopping arbitrarily large blobs.
            //
            // Not needed when drawingId names a level the server already has:
            // re-saving your own, or taking a copy of someone else's.
            'levelImage' => ['required_without:drawingId', 'nullable', File::image()->max(10 * 1024)],
            'drawingId' => ['nullable', 'integer'],

            // The slider positions saved with the drawing. The bounds are the
            // sliders' own min/max on the game page — anything outside them
            // never came from the interface.
            'speed' => ['required', 'integer', 'between:1,20'],
            'jumpHeight' => ['required', 'integer', 'between:5,30'],
        ];
    }
}
