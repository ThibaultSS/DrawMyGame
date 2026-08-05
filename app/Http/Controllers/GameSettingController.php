<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartGameRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GameSettingController extends Controller
{
    /**
     * The colour-picking screen. The image it shows is whatever the session
     * says was uploaded; arriving without one means the flow was entered
     * sideways, so the visit starts over at the upload form.
     */
    public function show(): Response|RedirectResponse
    {
        // The file can disappear underneath a session: playing someone else's
        // level points at their file, and they may delete it meanwhile. An
        // image that fails to load makes the eyedropper throw on every click,
        // so the flow restarts instead.
        if (! $this->levelFileExists()) {
            return redirect()->route('upload')->with('message', 'That level is no longer available.');
        }

        return Inertia::render('GameSetting', [
            'image' => route('uploaded-level'),
        ]);
    }

    public function store(StartGameRequest $request): RedirectResponse
    {
        session($request->validated());

        // Picking colours means starting fresh: a speed and jump left behind
        // by an earlier replayed drawing should not leak into this game.
        session()->forget(['gameSpeed', 'jumpHeight']);

        return redirect()->route('game');
    }

    private function levelFileExists(): bool
    {
        $path = session('uploadedLevel');

        return $path && Storage::disk('local')->exists($path);
    }
}
