<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteLevelRequest;
use App\Models\LevelPlay;
use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LevelPlayController extends Controller
{
    public function attempt(SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizePlaying($drawing);

        $play = LevelPlay::firstOrCreate(
            ['user_id' => Auth::id(), 'saved_drawing_id' => $drawing->id],
        );

        $play->increment('attempts');

        return back();
    }

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

    private function authorizePlaying(SavedDrawing $drawing): void
    {
        abort_unless($drawing->isPlayableBy(Auth::id()), 404);
    }
}
