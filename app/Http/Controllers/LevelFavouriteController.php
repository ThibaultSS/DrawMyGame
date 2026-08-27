<?php

namespace App\Http\Controllers;

use App\Http\Requests\FavouriteLevelRequest;
use App\Models\LevelFavourite;
use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LevelFavouriteController extends Controller
{
    /**
     * Keep somebody else's level to play again.
     *
     * This is what saving another person's level does now. It used to copy the
     * drawing into your account, which made you its owner and let you publish
     * it under your own name — not what anyone means by "save this".
     */
    public function store(FavouriteLevelRequest $request, SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizeFavouriting($drawing);

        // updateOrCreate, so pressing it again after moving the sliders keeps
        // the new feel rather than refusing as a duplicate.
        LevelFavourite::updateOrCreate(
            ['user_id' => Auth::id(), 'saved_drawing_id' => $drawing->id],
            [
                'speed' => $request->validated('speed'),
                'jump_height' => $request->validated('jumpHeight'),
            ],
        );

        return back()->with('message', 'Saved to your account.');
    }

    public function destroy(SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizeFavouriting($drawing);

        LevelFavourite::query()
            ->where('user_id', Auth::id())
            ->where('saved_drawing_id', $drawing->id)
            ->delete();

        return back()->with('message', 'Removed from your account.');
    }

    /**
     * Published levels only, and never your own.
     *
     * A 404 for anything unpublished, so an id cannot be probed for existence;
     * a 403 for your own, because a published drawing is not a secret and
     * "this is already yours" is the honest answer.
     */
    private function authorizeFavouriting(SavedDrawing $drawing): void
    {
        abort_unless($drawing->published, 404);
        abort_if($drawing->user_id === Auth::id(), 403);
    }
}
