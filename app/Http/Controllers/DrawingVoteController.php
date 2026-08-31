<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoteRequest;
use App\Models\DrawingVote;
use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DrawingVoteController extends Controller
{
    public function __invoke(VoteRequest $request, SavedDrawing $drawing): RedirectResponse
    {
        abort_unless($drawing->published, 404);

        abort_if($drawing->user_id === Auth::id(), 403);

        $value = (int) $request->validated('value');

        $existing = DrawingVote::query()
            ->where('user_id', Auth::id())
            ->where('saved_drawing_id', $drawing->id)
            ->first();

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
