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

        session(['hazardColor' => $request->validated('hazardColor')]);

        session()->forget(['gameSpeed', 'jumpHeight', 'replayDrawingId']);

        return redirect()->route('game');
    }

    private function replayedDrawing(): ?SavedDrawing
    {
        $drawing = SavedDrawing::find(session('replayDrawingId'));

        return $drawing?->isPlayableBy(Auth::id()) ? $drawing : null;
    }
}
