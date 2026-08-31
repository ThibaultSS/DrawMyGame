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
            $user->forceFill(['google_id' => $googleUser->id])->save();
        } else {
            $user = User::create([
                'name' => $googleUser->name,
                'username' => $this->availableUsername($googleUser),
                'email' => $googleUser->email,
                'google_id' => $googleUser->id,
                'password' => bcrypt(Str::random(40)),
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('home');
    }

    private function availableUsername(object $googleUser): string
    {
        $base = $this->sanitisedUsername(
            $googleUser->nickname ?? Str::before($googleUser->email, '@')
        );

        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $suffix++;

            $username = Str::limit($base, User::USERNAME_MAX - strlen((string) $suffix), '').$suffix;
        }

        return $username;
    }

    private function sanitisedUsername(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '', Str::ascii($name)) ?? '';

        $clean = Str::limit($clean, User::USERNAME_MAX, '');

        return strlen($clean) >= User::USERNAME_MIN
            ? $clean
            : 'player'.Str::lower(Str::random(6));
    }
}
