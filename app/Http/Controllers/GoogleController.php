<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::updateOrCreate(
            ['email' => $googleUser->email],
            [
                'name' => $googleUser->name,
                'username' => $this->availableUsername($googleUser),
                'google_id' => $googleUser->id,
                'password' => bcrypt(Str::random(24)),
            ]
        );

        Auth::login($user);

        // Same as the other two ways in: a fresh session id, so a session planted
        // before signing in cannot be reused afterwards.
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    /**
     * Google nicknames are not unique, but the username column is. Two people
     * whose Google nickname is "jochen" used to mean a constraint violation and a
     * 500 on the callback, so a free variant is picked instead.
     */
    private function availableUsername(object $googleUser): string
    {
        $base = $googleUser->nickname ?? Str::before($googleUser->email, '@');

        $username = $base;
        $suffix = 1;

        // Someone signing in again already owns their username, so that one still
        // counts as free for them.
        $taken = fn (string $candidate): bool => User::query()
            ->where('username', $candidate)
            ->where('email', '!=', $googleUser->email)
            ->exists();

        while ($taken($username)) {
            $username = $base.++$suffix;
        }

        return $username;
    }
}
