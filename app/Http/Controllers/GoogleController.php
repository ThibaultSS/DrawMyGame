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

        $user = User::firstWhere('email', $googleUser->email);

        if ($user) {
            // Only the link to Google is written. updateOrCreate would have
            // applied the whole payload to an existing row, so someone who
            // registered with a password and later signed in with Google had
            // their password replaced by a random hash and their username
            // rewritten — locked out of their own account, with no reset flow
            // to recover through.
            $user->forceFill(['google_id' => $googleUser->id])->save();
        } else {
            $user = User::create([
                'name' => $googleUser->name,
                'username' => $this->availableUsername($googleUser),
                'email' => $googleUser->email,
                'google_id' => $googleUser->id,
                // There is no password to log in with; signing in happens
                // through Google. A random one keeps the column non-null and
                // unguessable.
                'password' => bcrypt(Str::random(40)),
            ]);
        }

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

        while (User::where('username', $username)->exists()) {
            $username = $base.++$suffix;
        }

        return $username;
    }
}
