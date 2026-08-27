<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteLevelRequest;
use App\Models\LevelPlay;
use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LevelPlayController extends Controller
{
    /**
     * Somebody started this level.
     *
     * Recorded so "beaten by 12 of 40" has a denominator: without the attempts
     * there is no way to tell a hard level from an unpopular one.
     */
    public function attempt(SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizePlaying($drawing);

        $play = LevelPlay::firstOrCreate(
            ['user_id' => Auth::id(), 'saved_drawing_id' => $drawing->id],
        );

        $play->increment('attempts');

        return back();
    }

    /**
     * Somebody finished it.
     *
     * Only an improvement is written, so replaying a level you have already
     * beaten cannot cost you your best time.
     */
    public function complete(CompleteLevelRequest $request, SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizePlaying($drawing);

        $time = (int) $request->validated('timeMs');

        $play = LevelPlay::firstOrCreate(
            ['user_id' => Auth::id(), 'saved_drawing_id' => $drawing->id],
        );

        if ($play->best_time_ms === null || $time < $play->best_time_ms) {
            $play->update(['best_time_ms' => $time, 'completed_at' => now()]);
        }

        return back();
    }

    /**
     * A level you may play is a level you may be recorded against — published,
     * or unpublished and yours. A 404 for anything else, so an id cannot be
     * probed for existence.
     */
    private function authorizePlaying(SavedDrawing $drawing): void
    {
        abort_unless($drawing->isPlayableBy(Auth::id()), 404);
    }
}
