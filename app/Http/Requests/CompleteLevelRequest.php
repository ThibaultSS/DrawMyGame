<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteLevelRequest extends FormRequest
{
    /**
     * The route sits in the auth group, and the controller decides which levels
     * may be recorded against.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * How long the run took, in milliseconds.
     *
     * The clock runs in the browser, because the game does. That means a time
     * is **trusted input**: somebody determined can post one they did not earn,
     * and no server-side check can tell. The bounds below only keep out the
     * obviously impossible — a run cannot be instant, and one over an hour is a
     * tab left open rather than a race.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'timeMs' => ['required', 'integer', 'between:250,3600000'],
        ];
    }
}
