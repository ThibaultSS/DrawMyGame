<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadLevelRequest;
use Illuminate\Http\RedirectResponse;

class UploadLevelController extends Controller
{
    public function __invoke(UploadLevelRequest $request): RedirectResponse
    {
        // The private disk on purpose: level images are only reachable through
        // LevelImageController's checks, never by a direct storage URL.
        $path = $request
            ->file('levelImage')
            ->store('levels', 'local');

        session(['uploadedLevel' => $path]);

        // A new image invalidates whatever game the session was holding: the
        // old colours belong to the old picture, and stale ones would let
        // /game (or a Save there) run this image against them.
        session()->forget([
            'platformColor', 'goalColor', 'playerColor', 'hazardColor',
            'gameSpeed', 'jumpHeight',
        ]);

        return redirect()->route('game-setting');
    }
}
