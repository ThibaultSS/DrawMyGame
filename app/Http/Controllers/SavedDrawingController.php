<?php

namespace App\Http\Controllers;

use App\Models\SavedDrawing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SavedDrawingController extends Controller
{
    /**
     * Auth is the route group's job, so no check here. The response is a
     * redirect back with a flash message rather than JSON: the Game page saves
     * through Inertia, and the shared flash prop becomes its toast.
     */
    public function store(): RedirectResponse
    {
        // The save button should not be reachable without an upload in the
        // session, but a stale tab can still get here.
        if (! session()->has('uploadedLevel')) {
            return back()->with('message', 'There is no level to save.');
        }

        SavedDrawing::create([
            'user_id' => Auth::id(),
            'image_path' => session('uploadedLevel'),
        ]);

        return back()->with('message', 'Drawing saved.');
    }

    public function togglePublish(int $id): RedirectResponse
    {
        $drawing = $this->ownedDrawing($id);

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
     * Replaying re-enters the flow at colour picking. Published levels are
     * playable by everyone; unpublished ones only by their owner — 404 rather
     * than 403, so an id cannot be probed for existence.
     */
    public function play(SavedDrawing $drawing): RedirectResponse
    {
        abort_unless($drawing->published || $drawing->user_id === Auth::id(), 404);

        session(['uploadedLevel' => $drawing->image_path]);

        return redirect()->route('game-setting');
    }

    public function community(): Response
    {
        // with('user') because the author's name sits on every card; without it
        // this is one extra query per drawing.
        $drawings = SavedDrawing::query()
            ->with('user')
            ->where('published', true)
            ->latest()
            ->paginate(12)
            ->through(fn (SavedDrawing $drawing): array => [
                'id' => $drawing->id,
                'image' => route('drawings.image', $drawing),
                'author' => $drawing->user->username,
            ]);

        return Inertia::render('Community', [
            'drawings' => $drawings,
        ]);
    }

    public function index(): Response
    {
        // Only the fields the page draws. Shared props are serialised into the
        // page source, so user_id and the timestamps have no business there.
        $drawings = SavedDrawing::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(12)
            ->through(fn (SavedDrawing $drawing): array => [
                'id' => $drawing->id,
                'image' => route('drawings.image', $drawing),
                'published' => $drawing->published,
            ]);

        return Inertia::render('Account', [
            'drawings' => $drawings,
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        $drawing = $this->ownedDrawing($id);

        $drawing->delete();

        // Replaying a level copies its image_path into any new save, so a file
        // can be shared between rows — even across users. It is only removed
        // from disk once nothing else, live or trashed, still points at it.
        $stillReferenced = SavedDrawing::withTrashed()
            ->where('image_path', $drawing->image_path)
            ->whereKeyNot($drawing->id)
            ->exists();

        if (! $stillReferenced) {
            Storage::disk('local')->delete($drawing->image_path);
        }

        return back()->with('message', 'Drawing deleted.');
    }

    /**
     * Ownership is checked in the query, not after fetching, so a drawing that
     * belongs to someone else is a 404 rather than a 403 that confirms it exists.
     */
    private function ownedDrawing(int $id): SavedDrawing
    {
        return SavedDrawing::query()
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }
}
