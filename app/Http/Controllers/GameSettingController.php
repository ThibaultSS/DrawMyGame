<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartGameRequest;
use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GameSettingController extends Controller
{
    /**
     * The colour-picking screen.
     *
     * A freshly uploaded or drawn level is held by the browser, so there is no
     * image for the server to hand over — the page reads it from the level
     * store and sends the visitor back to /upload if it finds nothing.
     *
     * Replaying a drawing saved before the settings columns existed is the one
     * case that still lands here with a server-side image, so that one is
     * passed as a prop.
     */
    public function show(): Response
    {
        $replayed = $this->replayedDrawing();

        return Inertia::render('GameSetting', [
            'image' => $replayed ? route('drawings.image', $replayed) : null,
        ]);
    }

    public function store(StartGameRequest $request): RedirectResponse
    {
        session($request->validated());

        // Set explicitly, because validated() leaves out a key that was never
        // sent: an omitted hazard means "this level has none", and without this
        // the hazard colour from an earlier level would linger and be matched
        // against this one.
        session(['hazardColor' => $request->validated('hazardColor')]);

        // Picking colours means starting fresh: a speed and jump left behind
        // by an earlier replayed drawing should not leak into this game, and
        // this is no longer a replay of anything.
        session()->forget(['gameSpeed', 'jumpHeight', 'replayDrawingId']);

        return redirect()->route('game');
    }

    /**
     * The saved drawing the session is replaying, if the visitor may still play
     * it — a drawing can be unpublished or deleted between starting it and
     * arriving here.
     */
    private function replayedDrawing(): ?SavedDrawing
    {
        $drawing = SavedDrawing::find(session('replayDrawingId'));

        return $drawing?->isPlayableBy(Auth::id()) ? $drawing : null;
    }
}
