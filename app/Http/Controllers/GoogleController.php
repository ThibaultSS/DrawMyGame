<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::updateOrCreate(
            ['email' => $googleUser->email],
            [
                'name' => $googleUser->name,
                'username' => $googleUser->nickname ?? explode('@', $googleUser->email)[0],
                'google_id' => $googleUser->id,
                'password' => bcrypt(str()->random(24)),
            ]
        );

        Auth::login($user);

        return redirect('/');
    }
}