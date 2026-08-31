<?php

namespace App\Http\Controllers;

use App\Models\DrawingVote;
use App\Models\LevelPlay;
use App\Models\SavedDrawing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        if (! session()->has(['platformColor', 'goalColor', 'playerColor'])) {
            return redirect()->route('upload');
        }

        $replayId = session('replayDrawingId');
        $replayed = $replayId ? SavedDrawing::find($replayId) : null;

        if ($replayId && ! $replayed?->isPlayableBy(Auth::id())) {
            session()->forget('replayDrawingId');

            return redirect()->route('upload')->with('message', 'That level is no longer available.');
        }

        return Inertia::render('Game', [
            'levelImage' => $replayed ? route('drawings.image', $replayed) : null,
            'drawingId' => $replayed?->id,
            ...$this->voteSummary($replayed),
            ...$this->favouriteSummary($replayed),
            ...$this->playSummary($replayed),
            'platformColor' => session('platformColor'),
            'goalColor' => session('goalColor'),
            'playerColor' => session('playerColor'),
            'hazardColor' => session('hazardColor'),
            'speed' => (int) session('gameSpeed', 5),
            'jumpHeight' => (int) session('jumpHeight', 10),
        ]);
    }

    /**
     * @return array{beaten: int, attempted: int, myBestMs: int|null, fastest: array<int, array{username: string, ms: int}>, canRecord: bool}
     */
    private function playSummary(?SavedDrawing $drawing): array
    {
        if (! $drawing) {
            return ['beaten' => 0, 'attempted' => 0, 'myBestMs' => null, 'fastest' => [], 'canRecord' => false];
        }

        $drawing->loadCount([
            'plays as attempted_count',
            'plays as beaten_count' => fn (Builder $plays) => $plays->whereNotNull('best_time_ms'),
        ]);

        $fastest = LevelPlay::query()
            ->with('user')
            ->where('saved_drawing_id', $drawing->id)
            ->whereNotNull('best_time_ms')
            ->orderBy('best_time_ms')
            ->take(10)
            ->get()
            ->map(fn (LevelPlay $play): array => [
                'username' => $play->user?->username ?? 'Unknown player',
                'ms' => $play->best_time_ms,
            ])
            ->all();

        return [
            'beaten' => $drawing->beaten_count,
            'attempted' => $drawing->attempted_count,
            'myBestMs' => Auth::check()
                ? $drawing->plays()->where('user_id', Auth::id())->value('best_time_ms')
                : null,
            'fastest' => $fastest,
            'canRecord' => Auth::check(),
        ];
    }

    /**
     * @return array{isFavourite: bool, canFavourite: bool}
     */
    private function favouriteSummary(?SavedDrawing $drawing): array
    {
        if (! $drawing || ! Auth::check()) {
            return ['isFavourite' => false, 'canFavourite' => false];
        }

        return [
            'isFavourite' => $drawing->favourites()->where('user_id', Auth::id())->exists(),
            'canFavourite' => $drawing->published && $drawing->user_id !== Auth::id(),
        ];
    }

    /**
     * @return array{likes: int, dislikes: int, myVote: int|null, canVote: bool}
     */
    private function voteSummary(?SavedDrawing $drawing): array
    {
        if (! $drawing) {
            return ['likes' => 0, 'dislikes' => 0, 'myVote' => null, 'canVote' => false];
        }

        $drawing->loadCount([
            'votes as likes_count' => fn (Builder $votes) => $votes->where('value', DrawingVote::LIKE),
            'votes as dislikes_count' => fn (Builder $votes) => $votes->where('value', DrawingVote::DISLIKE),
        ]);

        $myVote = Auth::check()
            ? $drawing->votes()->where('user_id', Auth::id())->value('value')
            : null;

        return [
            'likes' => $drawing->likes_count,
            'dislikes' => $drawing->dislikes_count,
            'myVote' => $myVote === null ? null : (int) $myVote,
            'canVote' => $drawing->published && Auth::check() && $drawing->user_id !== Auth::id(),
        ];
    }
}
