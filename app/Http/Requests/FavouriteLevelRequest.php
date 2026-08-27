<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FavouriteLevelRequest extends FormRequest
{
    /**
     * The route sits in the auth group, and the controller decides whose levels
     * may be favourited.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The sliders' current positions, kept with the favourite so the level
     * opens the way you tuned it rather than the way its author did.
     *
     * Nullable: favouriting from the gallery, without having played it, sends
     * no settings and means "however the author left it".
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The bounds are the sliders' own min/max on the game page —
            // anything outside them never came from the interface.
            'speed' => ['nullable', 'integer', 'between:1,20'],
            'jumpHeight' => ['nullable', 'integer', 'between:5,30'],
        ];
    }
}
