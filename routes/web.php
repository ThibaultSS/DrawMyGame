<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DrawingVoteController;
use App\Http\Controllers\DrawnLevelController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameSettingController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LevelFavouriteController;
use App\Http\Controllers\LevelImageController;
use App\Http\Controllers\LevelPlayController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\RandomLevelController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\SavedDrawingController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Home')->name('home');
Route::inertia('/about', 'About')->name('about');
Route::inertia('/cookies', 'Cookies')->name('cookies');
Route::inertia('/upload', 'Upload')->name('upload');

Route::inertia('/login', 'Login')->middleware('guest')->name('login');

Route::get('/community', [SavedDrawingController::class, 'community'])->name('community');

Route::get('/draw', DrawnLevelController::class)->name('draw');

Route::get('/game-setting', [GameSettingController::class, 'show'])->name('game-setting');
Route::get('/play/{drawing}', [SavedDrawingController::class, 'play'])->name('play');

Route::get('/random-level', RandomLevelController::class)->name('play.random');
Route::get('/game', GameController::class)->name('game');

Route::get('/drawings/{drawing}/image', LevelImageController::class)->name('drawings.image');

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

Route::post('/start-game', [GameSettingController::class, 'store'])->name('start-game');
Route::post('/register', RegisteredUserController::class)
    ->middleware('throttle:5,1,registration')
    ->name('register');
Route::post('/login', LoginController::class)->name('login.attempt');
Route::post('/logout', LogoutController::class)->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('account');

    Route::patch('/account/username', [AccountController::class, 'updateUsername'])->name('account.username');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    Route::post('/save-drawing', [SavedDrawingController::class, 'store'])
        ->middleware('throttle:20,1,uploads')
        ->name('drawings.store');

    Route::post('/drawing/{drawing}/publish', [SavedDrawingController::class, 'publish'])->name('drawings.publish');
    Route::post('/drawing/{drawing}/unpublish', [SavedDrawingController::class, 'unpublish'])->name('drawings.unpublish');

    Route::post('/drawing/{drawing}/vote', DrawingVoteController::class)->name('drawings.vote');

    Route::post('/drawing/{drawing}/favourite', [LevelFavouriteController::class, 'store'])->name('drawings.favourite');
    Route::delete('/drawing/{drawing}/favourite', [LevelFavouriteController::class, 'destroy'])->name('drawings.unfavourite');

    Route::post('/drawing/{drawing}/attempt', [LevelPlayController::class, 'attempt'])
        ->middleware('throttle:60,1,plays')
        ->name('drawings.attempt');
    Route::post('/drawing/{drawing}/completed', [LevelPlayController::class, 'complete'])
        ->middleware('throttle:60,1,plays')
        ->name('drawings.completed');

    Route::delete('/drawing/{drawing}', [SavedDrawingController::class, 'destroy'])->name('drawings.destroy');
});
