<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\GameSettingController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LevelImageController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\SavedDrawingController;
use App\Http\Controllers\UploadLevelController;
use Illuminate\Support\Facades\Route;

// Static pages: Route::inertia renders the component directly, so there is no
// controller or closure to maintain for a page with no data.
Route::inertia('/', 'Home')->name('home');
Route::inertia('/about', 'About')->name('about');
Route::inertia('/upload', 'Upload')->name('upload');

// 'guest' sends anyone already signed in away instead of showing them the form.
Route::inertia('/login', 'Login')->middleware('guest')->name('login');

Route::get('/community', [SavedDrawingController::class, 'community'])->name('community');

// The colour-picking step. Both ways into it — a fresh upload and replaying a
// saved drawing — put the image path in the session first, then land here.
Route::get('/game-setting', [GameSettingController::class, 'show'])->name('game-setting');
Route::get('/play/{drawing}', [SavedDrawingController::class, 'play'])->name('play');
Route::get('/game', GameController::class)->name('game');

// Level images live on the private disk and are only served through these two
// routes, which check who may see what.
Route::get('/uploaded-level', [LevelImageController::class, 'current'])->name('uploaded-level');
Route::get('/drawings/{drawing}/image', [LevelImageController::class, 'drawing'])->name('drawings.image');

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

Route::post('/upload-level', UploadLevelController::class)->name('upload-level');
Route::post('/start-game', [GameSettingController::class, 'store'])->name('start-game');
Route::post('/register', RegisteredUserController::class)->name('register');
Route::post('/login', LoginController::class)->name('login.attempt');
Route::post('/logout', LogoutController::class)->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/account', [SavedDrawingController::class, 'index'])->name('account');
    Route::post('/save-drawing', [SavedDrawingController::class, 'store'])->name('drawings.store');
    Route::post('/drawing/{id}/publish', [SavedDrawingController::class, 'togglePublish'])->name('drawings.publish');
    Route::delete('/drawing/{id}', [SavedDrawingController::class, 'destroy'])->name('drawings.destroy');
});
