<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartGameRequest;
use Illuminate\Http\RedirectResponse;
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
        if (! session()->has('uploadedLevel')) {
            return redirect()->route('upload');
        }

        return Inertia::render('GameSetting', [
            'image' => route('uploaded-level'),
        ]);
    }

    public function store(StartGameRequest $request): RedirectResponse
    {
        session($request->validated());

        return redirect()->route('game');
    }
}
