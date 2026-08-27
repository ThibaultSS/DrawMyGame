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
    /**
     * The game runs client-side; this route only hands the engine what the
     * session has collected. Without the four colours there is nothing to
     * build, so the visit starts the flow over.
     *
     * The image is not the server's business unless the level is a saved one.
     * A level that has only been uploaded or drawn is held by the browser, so
     * levelImage comes back null and the page loads it from the level store.
     */
    public function __invoke(): Response|RedirectResponse
    {
        // Three colours, not four: a hazard is optional, and session()->has()
        // reports false for a null value — so asking for the hazard here would
        // send every hazard-less level straight back to the upload page.
        if (! session()->has(['platformColor', 'goalColor', 'playerColor'])) {
            return redirect()->route('upload');
        }

        $replayId = session('replayDrawingId');
        $replayed = $replayId ? SavedDrawing::find($replayId) : null;

        // The owner can unpublish or delete a drawing while someone else is
        // playing it. Without this the page would boot against an image that
        // 404s and render an empty world with no explanation.
        if ($replayId && ! $replayed?->isPlayableBy(Auth::id())) {
            session()->forget('replayDrawingId');

            return redirect()->route('upload')->with('message', 'That level is no longer available.');
        }

        return Inertia::render('Game', [
            'levelImage' => $replayed ? route('drawings.image', $replayed) : null,
            // Lets Save update this drawing instead of duplicating it.
            'drawingId' => $replayed?->id,
            ...$this->voteSummary($replayed),
            ...$this->favouriteSummary($replayed),
            ...$this->playSummary($replayed),
            'platformColor' => session('platformColor'),
            'goalColor' => session('goalColor'),
            'playerColor' => session('playerColor'),
            'hazardColor' => session('hazardColor'),
            // Where the sliders start. A replayed drawing plays as its author
            // tuned it; a fresh level starts at the defaults.
            'speed' => (int) session('gameSpeed', 5),
            'jumpHeight' => (int) session('jumpHeight', 10),
        ]);
    }

    /**
     * How this level has been played: how many have beaten it, out of how many
     * who tried, your own best, and the fastest few.
     *
     * All empty for a level the browser is holding — there is nothing to record
     * against until a level has been saved.
     *
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
            ->take(5)
            ->get()
            ->map(fn (LevelPlay $play): array => [
                // The author of a time can have deleted their account: the row
                // goes with them, but another player's row may still name them
                // in a race they are no longer part of.
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
            // A time has to belong to somebody, so this needs an account.
            'canRecord' => Auth::check(),
        ];
    }

    /**
     * Whether this is somebody else's level you have kept, and whether you
     * could keep it.
     *
     * Both false for a level the browser is holding: there is nothing to keep
     * until a level has been saved and published.
     *
     * @return array{isFavourite: bool, canFavourite: bool}
     */
    private function favouriteSummary(?SavedDrawing $drawing): array
    {
        if (! $drawing || ! Auth::check()) {
            return ['isFavourite' => false, 'canFavourite' => false];
        }

        return [
            'isFavourite' => $drawing->favourites()->where('user_id', Auth::id())->exists(),
            // Published, and not already yours — you cannot keep what you own.
            'canFavourite' => $drawing->published && $drawing->user_id !== Auth::id(),
        ];
    }

    /**
     * How this level stands, and whether this visitor may have a say.
     *
     * All of it is empty for a level the browser is holding: there is nothing
     * to vote on until a level has been saved and published.
     *
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
            // Voting needs an account, the level has to be public, and authors
            // do not rank their own work.
            'canVote' => $drawing->published && Auth::check() && $drawing->user_id !== Auth::id(),
        ];
    }
}
