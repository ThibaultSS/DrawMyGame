<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateUsernameRequest;
use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    /**
     * The username is the only name anyone else sees — it is on every card in
     * the community — so changing it updates those too, by being the same
     * column they read.
     */
    public function updateUsername(UpdateUsernameRequest $request): RedirectResponse
    {
        $request->user()->update(['username' => $request->validated('username')]);

        return back()->with('message', 'Username updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return back()->with('message', 'Password updated.');
    }

    /**
     * Deleting the account, but not everything it made.
     *
     * A level that was published is already out in the community — other people
     * have played it and voted on it — so it stays, and the author key becomes
     * null; the gallery then credits it to "Unknown publisher". Unpublished
     * drafts were never anyone else's business and go with the account.
     */
    public function destroy(DeleteAccountRequest $request): Response
    {
        $user = $request->user();

        $this->removeUnpublishedDrawings($user);

        Auth::logout();

        // The nullOnDelete constraint is what lets the published rows survive.
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // A full visit, like logging out: the new session and CSRF token are
        // then picked up cleanly.
        return Inertia::location(route('home'));
    }

    /**
     * Drafts and their images, gone for good — including any already in the
     * bin, since nothing will ever restore them now.
     */
    private function removeUnpublishedDrawings(User $user): void
    {
        $drafts = SavedDrawing::withTrashed()
            ->where('user_id', $user->id)
            ->where('published', false)
            ->get();

        foreach ($drafts as $draft) {
            $path = $draft->image_path;

            $draft->forceDelete();

            // One picture is one file and several drawings may point at it, so
            // the draft's image only goes if nothing else still names it. Live
            // rows only, for the same reason as destroy(): a trashed row would
            // otherwise pin a shared file forever.
            $stillReferenced = SavedDrawing::query()
                ->where('image_path', $path)
                ->exists();

            if (! $stillReferenced) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
