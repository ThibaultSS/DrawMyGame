<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
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

        return Inertia::render('Game', [
            'levelImage' => route('uploaded-level'),
            'platformColor' => session('platformColor'),
            'goalColor' => session('goalColor'),
            'playerColor' => session('playerColor'),
            'hazardColor' => session('hazardColor'),
        ]);
    }
}
