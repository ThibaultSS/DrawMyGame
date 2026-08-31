<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateUsernameRequest;
use App\Models\LevelFavourite;
use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    public function index(): InertiaResponse|RedirectResponse
    {
        $drawings = SavedDrawing::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(self::PER_PAGE);

        $favourites = LevelFavourite::query()
            ->where('user_id', Auth::id())
            ->whereHas('drawing', fn (Builder $drawing) => $drawing->where('published', true))
            ->with('drawing.user')
            ->latest()
            ->paginate(self::PER_PAGE, ['*'], 'favouritesPage');

        return $this->pageOutOfRange($drawings, 'account')
            ?? Inertia::render('Account', [
                'drawings' => $drawings->through(fn (SavedDrawing $drawing): array => [
                    'id' => $drawing->id,
                    'image' => route('drawings.image', $drawing),
                    'published' => $drawing->published,
                    'title' => $drawing->title,
                    'description' => $drawing->description,
                ]),
                'favourites' => $favourites->through(fn (LevelFavourite $favourite): array => [
                    'id' => $favourite->drawing->id,
                    'image' => route('drawings.image', $favourite->drawing),
                    'title' => $favourite->drawing->title,
                    'author' => $favourite->drawing->user?->username ?? 'Unknown publisher',
                ]),
            ]);
    }

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

    public function destroy(DeleteAccountRequest $request): Response
    {
        $user = $request->user();

        $this->removeUnpublishedDrawings($user);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('home'));
    }

    private function removeUnpublishedDrawings(User $user): void
    {
        SavedDrawing::withTrashed()
            ->where('user_id', $user->id)
            ->where('published', false)
            ->chunkById(100, function ($drafts): void {
                foreach ($drafts as $draft) {
                    $path = $draft->image_path;

                    $draft->forceDelete();

                    $stillReferenced = SavedDrawing::query()
                        ->where('image_path', $path)
                        ->exists();

                    if (! $stillReferenced) {
                        Storage::disk('local')->delete($path);
                    }
                }
            });
    }
}
