<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadLevelRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DrawnLevelController extends Controller
{
    /**
     * The colours the Draw page paints with, and therefore the colours the
     * engine is told to look for. One source of truth: the page renders its
     * buttons from this array (passed as a prop), and store() writes the same
     * values into the session — which is why a drawn level can skip the
     * eyedropper step entirely.
     *
     * They are far apart in RGB on purpose: the detector matches colours with
     * a tolerance of 70, so near neighbours would bleed into each other.
     */
    private const PALETTE = [
        'platform' => '#000000',
        'goal' => '#00aa00',
        'player' => '#0000ff',
        'hazard' => '#ff0000',
    ];

    public function show(): Response
    {
        return Inertia::render('Draw', [
            'palette' => self::PALETTE,
        ]);
    }

    /**
     * The drawn canvas arrives as an ordinary PNG upload, so the same
     * validation as a photographed level applies.
     */
    public function store(UploadLevelRequest $request): RedirectResponse
    {
        $path = $request
            ->file('levelImage')
            ->store('levels', 'local');

        session([
            'uploadedLevel' => $path,
            'platformColor' => self::PALETTE['platform'],
            'goalColor' => self::PALETTE['goal'],
            'playerColor' => self::PALETTE['player'],
            'hazardColor' => self::PALETTE['hazard'],
        ]);

        // A drawn level starts with the default feel; the sliders on the game
        // page take it from there.
        session()->forget(['gameSpeed', 'jumpHeight']);

        return redirect()->route('game');
    }
}
