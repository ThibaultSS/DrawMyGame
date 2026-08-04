<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /**
     * Failed attempts allowed per email address and IP before the form locks.
     */
    private const MAX_ATTEMPTS = 5;

    public function __invoke(LoginRequest $request): RedirectResponse
    {
        $throttleKey = $this->throttleKey($request);

        // A generic error message stops the form being used to find out which
        // addresses have an account. On its own it does not stop anyone simply
        // trying passwords until one works, which is what this limit is for.
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt($request->validated())) {
            RateLimiter::hit($throttleKey);

            return back()->withErrors([
                'email' => 'Email or password is incorrect.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        // intended() returns wherever the user was headed when they were bounced
        // to the login page, falling back to the home page. Every page is an
        // Inertia page now, so a plain redirect is enough: Inertia follows it
        // and swaps the page component in place.
        return redirect()->intended(route('home'));
    }

    /**
     * Rate limit per address and IP together, so one person guessing at an account
     * cannot lock its real owner out from somewhere else.
     */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower($request->string('email')).'|'.$request->ip()
        );
    }
}
