<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /**
     * Failed attempts allowed per email address and IP before the form locks.
     */
    private const MAX_ATTEMPTS = 5;

    public function __invoke(Request $request): Response
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

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

        if (! Auth::attempt($request->only('email', 'password'))) {
            RateLimiter::hit($throttleKey);

            return back()->withErrors([
                'email' => 'Email or password is incorrect.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        // intended() returns wherever the user was headed when they were bounced to
        // the login page, falling back to the home page. Without it, someone who
        // asked for /account lands on / instead and has to navigate again.
        //
        // Inertia::location rather than the redirect itself: the target is still a
        // Blade page, and an Inertia request that receives plain HTML throws. It
        // still returns an ordinary redirect for non-Inertia requests.
        return Inertia::location(redirect()->intended('/')->getTargetUrl());
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
