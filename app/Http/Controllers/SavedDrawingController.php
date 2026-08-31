<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishDrawingRequest;
use App\Http\Requests\SaveDrawingRequest;
use App\Models\DrawingVote;
use App\Models\SavedDrawing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SavedDrawingController extends Controller
{
    public function store(SaveDrawingRequest $request): RedirectResponse
    {
        if (! session()->has(['platformColor', 'goalColor', 'playerColor', 'hazardColor'])) {
            return back()->with('message', 'There is no level to save.');
        }

        $settings = [
            'platform_color' => session('platformColor'),
            'goal_color' => session('goalColor'),
            'player_color' => session('playerColor'),
            'hazard_color' => session('hazardColor'),
            'speed' => $request->validated('speed'),
            'jump_height' => $request->validated('jumpHeight'),
        ];

        $replayed = $this->playableDrawing($request->validated('drawingId'));

        if ($replayed && $replayed->user_id === Auth::id()) {
            $replayed->update($settings);

            return back()->with('message', 'Drawing updated.');
        }

        abort_if($replayed !== null, 403);

        $path = $this->storeByContent($request->file('levelImage'));

        $drawing = $this->createDrawing($path, $settings);

        session(['replayDrawingId' => $drawing->id]);

        return back()->with('message', 'Drawing saved.');
    }

    public function publish(PublishDrawingRequest $request, SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizeOwner($drawing);

        $wasPublished = $drawing->published;

        $drawing->update([
            'published' => true,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
        ]);

        return back()->with('message', $wasPublished ? 'Details updated.' : 'Drawing published.');
    }

    public function unpublish(SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizeOwner($drawing);

        $drawing->update(['published' => false]);

        return back()->with('message', 'Drawing unpublished.');
    }

    public function play(SavedDrawing $drawing): RedirectResponse
    {
        abort_unless($drawing->isPlayableBy(Auth::id()), 404);

        session(['replayDrawingId' => $drawing->id]);

        if (! $drawing->hasGameSettings()) {
            session()->forget([
                'platformColor', 'goalColor', 'playerColor', 'hazardColor',
                'gameSpeed', 'jumpHeight',
            ]);

            return redirect()->route('game-setting');
        }

        $mine = Auth::check()
            ? $drawing->favourites()->where('user_id', Auth::id())->first()
            : null;

        session([
            'platformColor' => $drawing->platform_color,
            'goalColor' => $drawing->goal_color,
            'playerColor' => $drawing->player_color,
            'hazardColor' => $drawing->hazard_color,
            'gameSpeed' => $mine?->speed ?? $drawing->speed ?? 5,
            'jumpHeight' => $mine?->jump_height ?? $drawing->jump_height ?? 10,
        ]);

        return redirect()->route('game');
    }

    public function community(Request $request): Response|RedirectResponse
    {
        $search = trim((string) $request->query('search', ''));
        $sort = $request->query('sort') === 'liked' ? 'liked' : 'newest';

        $drawings = SavedDrawing::query()
            ->with('user')
            ->where('published', true)
            ->withCount([
                'votes as likes_count' => fn (Builder $votes) => $votes->where('value', DrawingVote::LIKE),
                'votes as dislikes_count' => fn (Builder $votes) => $votes->where('value', DrawingVote::DISLIKE),
                'plays as beaten_count' => fn (Builder $plays) => $plays->whereNotNull('best_time_ms'),
                'plays as attempted_count',
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $match) => $match
                    ->where('title', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('username', 'like', "%{$search}%"))
            ))
            ->when($sort === 'liked', fn (Builder $query) => $query->orderByDesc($this->voteScore()))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return $this->pageOutOfRange($drawings, 'community')
            ?? Inertia::render('Community', [
                'drawings' => $drawings->through(fn (SavedDrawing $drawing): array => [
                    'id' => $drawing->id,
                    'image' => route('drawings.image', $drawing),
                    'title' => $drawing->title,
                    'description' => $drawing->description,
                    'author' => $drawing->user?->username ?? 'Unknown publisher',
                    'likes' => $drawing->likes_count,
                    'dislikes' => $drawing->dislikes_count,
                    'beaten' => $drawing->beaten_count,
                    'attempted' => $drawing->attempted_count,
                ]),
                'filters' => ['search' => $search, 'sort' => $sort],
            ]);
    }

    public function destroy(SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizeOwner($drawing);

        $path = $drawing->image_path;

        $drawing->delete();

        $stillReferenced = SavedDrawing::query()
            ->where('image_path', $path)
            ->exists();

        if (! $stillReferenced) {
            Storage::disk('local')->delete($path);
        }

        return back()->with('message', 'Drawing deleted.');
    }

    /**
     * @return Builder<DrawingVote>
     */
    private function voteScore(): Builder
    {
        return DrawingVote::query()
            ->selectRaw('coalesce(sum(value), 0)')
            ->whereColumn('saved_drawing_id', 'saved_drawings.id');
    }

    private function authorizeOwner(SavedDrawing $drawing): void
    {
        abort_unless($drawing->user_id === Auth::id(), 404);
    }

    private function playableDrawing(?int $id): ?SavedDrawing
    {
        if (! $id) {
            return null;
        }

        $drawing = SavedDrawing::find($id);

        abort_unless($drawing?->isPlayableBy(Auth::id()), 404);

        return $drawing;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function createDrawing(string $path, array $settings): SavedDrawing
    {
        try {
            return SavedDrawing::create([
                'user_id' => Auth::id(),
                'image_path' => $path,
                ...$settings,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    private function storeByContent(UploadedFile $file): string
    {
        $path = 'levels/'.hash_file('sha256', $file->getRealPath()).'.'.$file->extension();

        if (! Storage::disk('local')->exists($path)) {
            $file->storeAs('levels', basename($path), 'local');
        }

        return $path;
    }
}
