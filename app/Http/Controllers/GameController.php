<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /**
     * The game itself runs client-side; this route only hands the engine what
     * the session has collected. Without an uploaded level and all four
     * colours there is nothing to build, so the visit starts the flow over.
     */
    public function __invoke(): Response|RedirectResponse
    {
        if (! session()->has(['uploadedLevel', 'platformColor', 'goalColor', 'playerColor', 'hazardColor'])) {
            return redirect()->route('upload');
        }

        // The file can be deleted by its owner while someone else is playing
        // it. Without this the engine boots against a missing texture and
        // renders an empty world with no explanation.
        if (! Storage::disk('local')->exists(session('uploadedLevel'))) {
            return redirect()->route('upload')->with('message', 'That level is no longer available.');
        }

        return Inertia::render('Game', [
            'levelImage' => route('uploaded-level'),
            'platformColor' => session('platformColor'),
            'goalColor' => session('goalColor'),
            'playerColor' => session('playerColor'),
            'hazardColor' => session('hazardColor'),
            // Where the sliders start. A replayed drawing plays as its author
            // tuned it; a fresh level starts at the defaults.
            'speed' => (int) session('gameSpeed', 5),
            'jumpHeight' => (int) session('jumpHeight', 10),
        ]);
    }
}
