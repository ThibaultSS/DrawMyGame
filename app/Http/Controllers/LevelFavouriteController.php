<?php

namespace App\Http\Controllers;

use App\Http\Requests\FavouriteLevelRequest;
use App\Models\LevelFavourite;
use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LevelFavouriteController extends Controller
{
    public function store(FavouriteLevelRequest $request, SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizeFavouriting($drawing);

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

    private function authorizeFavouriting(SavedDrawing $drawing): void
    {
        abort_unless($drawing->published, 404);
        abort_if($drawing->user_id === Auth::id(), 403);
    }
}
