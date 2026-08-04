<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // One message whether the address is unknown or the password is wrong.
        // Saying which of the two it was lets anyone test whether an email address
        // has an account here, one guess at a time.
        if (! Auth::attempt($request->only('email', 'password'))) {
            return back()->withErrors([
                'email' => 'Email or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();

        // Not redirect(): the home page is still Blade, and an Inertia request that
        // receives plain HTML throws. Inertia::location forces a full page visit,
        // and still returns an ordinary redirect for non-Inertia requests.
        return Inertia::location('/');
    }
}
