<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RegisteredUserController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            // Password::defaults() is at least 8 characters. Before this, 'a' was a
            // valid password.
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->username,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        // Same as after a login: a fresh session id, so a session planted before
        // signing up cannot be reused afterwards.
        $request->session()->regenerate();

        // Same reason as the login redirect: the home page is not an Inertia page yet.
        return Inertia::location('/');
    }
}
