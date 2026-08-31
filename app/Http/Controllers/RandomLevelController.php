<?php

namespace App\Http\Controllers;

use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;

class RandomLevelController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $published = SavedDrawing::query()->where('published', true);

        $count = $published->count();

        if ($count === 0) {
            return back()->with('message', 'There are no published levels yet. Be the first.');
        }

        $drawing = $published->skip(random_int(0, $count - 1))->first();

        return redirect()->route('play', $drawing);
    }
}
