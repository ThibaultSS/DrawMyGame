<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDrawingRequest;
use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SavedDrawingController extends Controller
{
    /**
     * How many cards a gallery page holds.
     */
    private const PER_PAGE = 12;

    /**
     * Auth is the route group's job, so no check here. The response is a
     * redirect back with a flash message rather than JSON: the Game page saves
     * through Inertia, and the shared flash prop becomes its toast.
     *
     * The whole game goes with the drawing: the four colours the session
     * collected and the speed and jump the sliders were on when Save was
     * pressed. That is what lets a replay — yours or anyone's from the
     * community — start immediately instead of at the colour picker.
     */
    public function store(SaveDrawingRequest $request): RedirectResponse
    {
        $path = session('uploadedLevel');

        // The save button should not be reachable without an upload in the
        // session, but a stale tab can still get here.
        if (! $path || ! Storage::disk('local')->exists($path)) {
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

        // Replaying your own drawing and pressing Save means "keep this feel",
        // not "give me a duplicate": the existing row is updated in place.
        // This is also how a drawing from before the settings columns existed
        // gains them. Anyone else's drawing falls through and is saved as a
        // copy of their own.
        $own = SavedDrawing::query()
            ->where('user_id', Auth::id())
            ->where('image_path', $path)
            ->first();

        if ($own) {
            $own->update($settings);

            return back()->with('message', 'Drawing updated.');
        }

        SavedDrawing::create([
            'user_id' => Auth::id(),
            'image_path' => $this->fileForNewDrawing($path),
            ...$settings,
        ]);

        return back()->with('message', 'Drawing saved.');
    }

    public function togglePublish(SavedDrawing $drawing): RedirectResponse
    {
        $this->authorizeOwner($drawing);

        $drawing->published = ! $drawing->published;
        $drawing->save();

        // back() rather than a redirect to /account: Inertia re-fetches the page
        // props and Vue re-renders the one card that changed, so the scroll
        // position survives. The message is picked up from the shared flash prop.
        return back()->with(
            'message',
            $drawing->published ? 'Drawing published.' : 'Drawing unpublished.'
        );
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
        abort_unless($drawing->published || $drawing->user_id === Auth::id(), 404);

        session(['uploadedLevel' => $drawing->image_path]);

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

    public function community(): Response|RedirectResponse
    {
        // with('user') because the author's name sits on every card; without it
        // this is one extra query per drawing.
        $drawings = SavedDrawing::query()
            ->with('user')
            ->where('published', true)
            ->latest()
            ->paginate(self::PER_PAGE);

        return $this->pageOutOfRange($drawings, 'community')
            ?? Inertia::render('Community', [
                'drawings' => $drawings->through(fn (SavedDrawing $drawing): array => [
                    'id' => $drawing->id,
                    'image' => route('drawings.image', $drawing),
                    'author' => $drawing->user->username,
                ]),
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
     * Ownership is a 404 rather than a 403, so an id that belongs to someone
     * else cannot be told apart from one that does not exist.
     */
    private function authorizeOwner(SavedDrawing $drawing): void
    {
        abort_unless($drawing->user_id === Auth::id(), 404);
    }

    /**
     * The path to store on a new drawing.
     *
     * Playing someone else's level points the session at *their* file. Saving
     * from there has to take a copy: sharing one file between two owners means
     * neither can really delete it, and the original owner's delete would leave
     * their drawing on display under the other person's name.
     */
    private function fileForNewDrawing(string $path): string
    {
        $borrowed = SavedDrawing::withTrashed()
            ->where('image_path', $path)
            ->exists();

        if (! $borrowed) {
            return $path;
        }

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
