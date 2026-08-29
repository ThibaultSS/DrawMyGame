<?php

namespace App\Http\Controllers;

use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;

class RandomLevelController extends Controller
{
    /**
     * Hands you a published level, chosen at random.
     *
     * Its own path rather than /play/random: that would be caught by
     * /play/{drawing}, whose model binding would take "random" for an id.
     *
     * A count and an offset rather than inRandomOrder(), which asks the
     * database to sort every published level to hand back one row.
     */
    public function __invoke(): RedirectResponse
    {
        $published = SavedDrawing::query()->where('published', true);

        $count = $published->count();

        // Nothing published yet. A 404 would be wrong — the route exists and
        // there is simply nothing behind it — so this says so instead.
        if ($count === 0) {
            return back()->with('message', 'There are no published levels yet. Be the first.');
        }

        $drawing = $published->skip(random_int(0, $count - 1))->first();

        return redirect()->route('play', $drawing);
    }
}
