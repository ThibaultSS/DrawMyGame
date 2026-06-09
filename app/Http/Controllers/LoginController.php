<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class LoginController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'No account found with this email address.']);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return back()->withErrors(['password' => 'The password is incorrect.']);
        }

        $request->session()->regenerate();
        return redirect('/');
    }
}