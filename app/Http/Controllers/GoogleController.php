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
     * A username from Google that this application would also have accepted.
     *
     * This is the one place a username is written without passing through a
     * form request, so everything the rules guarantee has to be done by hand:
     * Google supplies a display name, which can be any length and hold spaces,
     * accents or punctuation, and none of that is allowed here.
     *
     * Google nicknames are not unique either, but the column is. Two people
     * whose nickname is "jochen" used to mean a constraint violation and a 500
     * on the callback, so a free variant is picked instead.
     */
    private function availableUsername(object $googleUser): string
    {
        $base = $this->sanitisedUsername(
            $googleUser->nickname ?? Str::before($googleUser->email, '@')
        );

        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $suffix++;

            // The suffix has to fit inside the limit as well, so the base is
            // trimmed to make room for it. Without this a name at the limit
            // would grow past it while trying to become unique — and be written
            // anyway, because nothing validates this path.
            $username = Str::limit($base, User::USERNAME_MAX - strlen((string) $suffix), '').$suffix;
        }

        return $username;
    }

    /**
     * Strips a display name down to what a username may hold, and pads it out
     * if too little survives.
     */
    private function sanitisedUsername(string $name): string
    {
        // Accents become their plain letters rather than being dropped, so
        // "Jürgen" is "Jurgen" and not "Jrgen".
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '', Str::ascii($name)) ?? '';

        $clean = Str::limit($clean, User::USERNAME_MAX, '');

        // A name of nothing but punctuation leaves nothing behind, and a
        // one-letter one is under the minimum. Either way it needs a name it
        // can actually be given.
        return strlen($clean) >= User::USERNAME_MIN
            ? $clean
            : 'player'.Str::lower(Str::random(6));
    }
}
