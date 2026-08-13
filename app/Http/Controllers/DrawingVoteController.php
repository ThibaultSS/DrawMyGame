<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoteRequest;
use App\Models\DrawingVote;
use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DrawingVoteController extends Controller
{
    /**
     * Like or dislike a published drawing, from the game page while playing it.
     *
     * No flash message: the button filling in and the count moving say it
     * better than a toast would, and a toast on every click would be noise.
     */
    public function __invoke(VoteRequest $request, SavedDrawing $drawing): RedirectResponse
    {
        // Only published levels can be voted on. An unpublished one is its
        // owner's business, and a 404 keeps its id from being probed.
        abort_unless($drawing->published, 404);

        // Authors do not rank their own work. A 403 rather than a 404 here:
        // the drawing is public, so its existence is not a secret.
        abort_if($drawing->user_id === Auth::id(), 403);

        $value = (int) $request->validated('value');

        $existing = DrawingVote::query()
            ->where('user_id', Auth::id())
            ->where('saved_drawing_id', $drawing->id)
            ->first();

        // Pressing the same button again means "never mind". There is no third
        // button for taking a vote back, so this is it.
        if ($existing?->value === $value) {
            $existing->delete();

            return back();
        }

        DrawingVote::updateOrCreate(
            ['user_id' => Auth::id(), 'saved_drawing_id' => $drawing->id],
            ['value' => $value],
        );

        return back();
    }
}
