<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LogoutController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Auth::logout();

        // Logging out has to throw the session away, not just forget who owns
        // it. Without invalidate() the session record survives, and without
        // regenerateToken() the old CSRF token stays valid.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Inertia::location makes the browser do a full visit, so the new
        // session and its CSRF token are picked up cleanly.
        return Inertia::location(route('home'));
    }
}
