<?php

use App\Http\Controllers\DrawnLevelController;
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

// Drawing a level in the browser instead of photographing paper. The palette
// is fixed, so a drawn level skips colour picking and starts straight away.
Route::get('/draw', [DrawnLevelController::class, 'show'])->name('draw');

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

// Throttled: uploading needs no account, and each accepted file is up to 10 MB
// of disk. levels:prune sweeps up whatever is never saved. The third throttle
// parameter names the bucket — without it, every unnamed throttle on the site
// shares one counter per visitor, so five draws would lock registration.
Route::post('/upload-level', UploadLevelController::class)
    ->middleware('throttle:20,1,uploads')
    ->name('upload-level');
Route::post('/draw-level', [DrawnLevelController::class, 'store'])
    ->middleware('throttle:20,1,draws')
    ->name('draw-level');
Route::post('/start-game', [GameSettingController::class, 'store'])->name('start-game');
// Throttled like the login form: registering is free and signs you straight
// in, so without a limit accounts can be created in bulk by a script.
Route::post('/register', RegisteredUserController::class)
    ->middleware('throttle:5,1,registration')
    ->name('register');
Route::post('/login', LoginController::class)->name('login.attempt');
Route::post('/logout', LogoutController::class)->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/account', [SavedDrawingController::class, 'index'])->name('account');
    Route::post('/save-drawing', [SavedDrawingController::class, 'store'])->name('drawings.store');

    // Route model binding, like /play: an id that is not a number is a 404
    // rather than a TypeError on the controller's int parameter.
    Route::post('/drawing/{drawing}/publish', [SavedDrawingController::class, 'togglePublish'])->name('drawings.publish');
    Route::delete('/drawing/{drawing}', [SavedDrawingController::class, 'destroy'])->name('drawings.destroy');
});
