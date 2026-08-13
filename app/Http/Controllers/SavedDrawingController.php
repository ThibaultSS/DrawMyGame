<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishDrawingRequest;
use App\Http\Requests\SaveDrawingRequest;
use App\Models\DrawingVote;
use App\Models\SavedDrawing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SavedDrawingController extends Controller
{
    /**
     * How many cards a gallery page holds.
     */
    private const PER_PAGE = 12;

    /**
     * Saving is the moment a level becomes the server's problem. Until now the
     * browser has been holding it, which is why this is the only route that
     * accepts an image: the nine in ten levels nobody keeps never arrive.
     *
     * The whole game goes with it — the four colours the session collected and
     * the speed and jump the sliders were on. That is what lets a replay, yours
     * or anyone's from the community, start immediately instead of at the
     * colour picker.
     */
    public function store(SaveDrawingRequest $request): RedirectResponse
    {
        // /game guards on these, but a stale tab can still get here.
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

        // Re-saving your own drawing means "keep this feel", not "give me a
        // duplicate". It is also how a drawing from before the settings columns
        // existed gains them.
        if ($replayed && $replayed->user_id === Auth::id()) {
            $replayed->update($settings);

            return back()->with('message', 'Drawing updated.');
        }

        // Saving someone else's level takes a copy of their file: sharing one
        // file between two owners means neither can really delete it, and the
        // original owner's delete would leave their drawing on display under
        // the other person's name. The server already has that image, so the
        // browser is not asked to upload it again.
        //
        // Anything else is a level the browser was holding, arriving here as an
        // ordinary upload.
        $path = $replayed
            ? $this->copyOf($replayed->image_path)
            : $request->file('levelImage')->store('levels', 'local');

        $drawing = $this->createDrawing($path, $settings);

        // This session is now playing the saved drawing, so pressing Save again
        // updates it instead of storing a second copy.
        session(['replayDrawingId' => $drawing->id]);

        return back()->with('message', 'Drawing saved.');
    }

    /**
     * Publishing takes a title and, if you want one, a description — a card in
     * the gallery with neither says nothing about what the level is.
     *
     * Posting this again while already published is how those details are
     * edited; there is no separate route for it, because "publish it, with this
     * text" describes both.
     *
     * back() rather than a redirect to /account: Inertia re-fetches the page
     * props and Vue re-renders the one card that changed, so the scroll
     * position survives. The message is picked up from the shared flash prop.
     */
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

    /**
     * The title and description are left in place: taking a level out of the
     * gallery for a while should not mean writing them again to put it back.
     */
    public function unpublish(SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizeOwner($drawing);

        $drawing->update(['published' => false]);

        return back()->with('message', 'Drawing unpublished.');
    }

    /**
     * Published levels are playable by everyone; unpublished ones only by
     * their owner — 404 rather than 403, so an id cannot be probed for
     * existence.
     *
     * A drawing saved with its game settings starts playing immediately, as
     * its author tuned it. Only drawings from before the settings columns
     * existed still detour through colour picking.
     */
    public function play(SavedDrawing $drawing): RedirectResponse
    {
        abort_unless($drawing->isPlayableBy(Auth::id()), 404);

        // The image stays on the server for a saved drawing, so the game and
        // the colour picker are told which one to ask for rather than reading
        // it out of the browser's level store.
        session(['replayDrawingId' => $drawing->id]);

        if (! $drawing->hasGameSettings()) {
            // The level just changed, so whatever game the session was holding
            // no longer describes it. Left in place, those stale colours let
            // /game boot this image against another picture's palette — and a
            // Save would bake that mismatch into an instant-play drawing.
            session()->forget([
                'platformColor', 'goalColor', 'playerColor', 'hazardColor',
                'gameSpeed', 'jumpHeight',
            ]);

            return redirect()->route('game-setting');
        }

        session([
            'platformColor' => $drawing->platform_color,
            'goalColor' => $drawing->goal_color,
            'playerColor' => $drawing->player_color,
            'hazardColor' => $drawing->hazard_color,
            'gameSpeed' => $drawing->speed ?? 5,
            'jumpHeight' => $drawing->jump_height ?? 10,
        ]);

        return redirect()->route('game');
    }

    public function community(Request $request): Response|RedirectResponse
    {
        $search = trim((string) $request->query('search', ''));
        $sort = $request->query('sort') === 'liked' ? 'liked' : 'newest';

        // with('user') because the author's name sits on every card; without it
        // this is one extra query per drawing.
        $drawings = SavedDrawing::query()
            ->with('user')
            ->where('published', true)
            ->withCount([
                'votes as likes_count' => fn (Builder $votes) => $votes->where('value', DrawingVote::LIKE),
                'votes as dislikes_count' => fn (Builder $votes) => $votes->where('value', DrawingVote::DISLIKE),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(
                // Nested on purpose: without the grouping the orWhere would
                // escape the published filter above it, and unpublished
                // drawings would appear as soon as anyone searched.
                fn (Builder $match) => $match
                    ->where('title', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('username', 'like', "%{$search}%"))
            ))
            // Ordered by net score rather than raw likes, so a level with ten
            // likes and nine dislikes does not outrank a quietly good one.
            ->when($sort === 'liked', fn (Builder $query) => $query->orderByDesc($this->voteScore()))
            // Always applied, so it breaks ties among equally liked levels.
            ->latest()
            ->paginate(self::PER_PAGE)
            // Without this, page two drops the search and the sort.
            ->withQueryString();

        return $this->pageOutOfRange($drawings, 'community')
            ?? Inertia::render('Community', [
                'drawings' => $drawings->through(fn (SavedDrawing $drawing): array => [
                    'id' => $drawing->id,
                    'image' => route('drawings.image', $drawing),
                    'title' => $drawing->title,
                    'description' => $drawing->description,
                    // The author is gone when they deleted their account: the
                    // level stays because it was already public.
                    'author' => $drawing->user?->username ?? 'Unknown publisher',
                    'likes' => $drawing->likes_count,
                    'dislikes' => $drawing->dislikes_count,
                ]),
                'filters' => ['search' => $search, 'sort' => $sort],
            ]);
    }

    public function index(): Response|RedirectResponse
    {
        $drawings = SavedDrawing::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(self::PER_PAGE);

        // Only the fields the page draws. Shared props are serialised into the
        // page source, so user_id and the timestamps have no business there.
        return $this->pageOutOfRange($drawings, 'account')
            ?? Inertia::render('Account', [
                'drawings' => $drawings->through(fn (SavedDrawing $drawing): array => [
                    'id' => $drawing->id,
                    'image' => route('drawings.image', $drawing),
                    'published' => $drawing->published,
                    // The publish form is prefilled from these, so editing a
                    // published level starts from what it already says.
                    'title' => $drawing->title,
                    'description' => $drawing->description,
                ]),
            ]);
    }

    public function destroy(SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizeOwner($drawing);

        $path = $drawing->image_path;

        $drawing->delete();

        // The page warns that deleting cannot be undone, so the file really
        // goes. whereKeyNot excludes the row just soft-deleted, which would
        // otherwise match itself and keep every file alive forever. store()
        // takes a copy when saving someone else's level, so in practice each
        // row owns its file; this guard is what makes that assumption safe.
        $stillReferenced = SavedDrawing::withTrashed()
            ->where('image_path', $path)
            ->whereKeyNot($drawing->id)
            ->exists();

        if (! $stillReferenced) {
            Storage::disk('local')->delete($path);
        }

        return back()->with('message', 'Drawing deleted.');
    }

    /**
     * A drawing's standing, as one correlated subquery: likes minus dislikes,
     * and 0 when nobody has voted.
     *
     * A subquery rather than ordering by the withCount aliases above, because
     * whether a database lets ORDER BY reference a select alias is a detail of
     * the database rather than something worth relying on.
     *
     * @return Builder<DrawingVote>
     */
    private function voteScore(): Builder
    {
        return DrawingVote::query()
            ->selectRaw('coalesce(sum(value), 0)')
            ->whereColumn('saved_drawing_id', 'saved_drawings.id');
    }

    /**
     * Ownership is a 404 rather than a 403, so an id that belongs to someone
     * else cannot be told apart from one that does not exist.
     */
    private function authorizeOwner(SavedDrawing $drawing): void
    {
        abort_unless($drawing->user_id === Auth::id(), 404);
    }

    /**
     * The drawing a save says it is replaying.
     *
     * It comes from the page rather than the session because a second tab can
     * move the session on to another level; the page knows which drawing it is
     * actually showing. Anything the visitor may not play is a 404, so the id
     * is worth no more than a guess.
     */
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
     * Stores a new drawing, and does not leave its file behind if the row
     * cannot be written — an image nothing points at is exactly the orphan this
     * whole flow exists to avoid.
     *
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

    /** Duplicates a level image so the new drawing owns its own file. */
    private function copyOf(string $path): string
    {
        $copy = 'levels/'.Str::random(40).'.'.pathinfo($path, PATHINFO_EXTENSION);

        Storage::disk('local')->copy($path, $copy);

        return $copy;
    }

    /**
     * Deleting the last drawing on the final page leaves the visitor on a page
     * that no longer exists, which renders as "you have no drawings" while they
     * still have plenty. Send them to the last page that does exist.
     *
     * @param  LengthAwarePaginator<int, SavedDrawing>  $drawings
     */
    private function pageOutOfRange(LengthAwarePaginator $drawings, string $routeName): ?RedirectResponse
    {
        if ($drawings->isNotEmpty() || $drawings->currentPage() <= $drawings->lastPage()) {
            return null;
        }

        return redirect()->route($routeName, ['page' => $drawings->lastPage()]);
    }
}
