<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DrawingVoteController;
use App\Http\Controllers\DrawnLevelController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameSettingController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LevelImageController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\SavedDrawingController;
use Illuminate\Support\Facades\Route;

// Static pages: Route::inertia renders the component directly, so there is no
// controller or closure to maintain for a page with no data.
Route::inertia('/', 'Home')->name('home');
Route::inertia('/about', 'About')->name('about');
Route::inertia('/cookies', 'Cookies')->name('cookies');
Route::inertia('/upload', 'Upload')->name('upload');

// 'guest' sends anyone already signed in away instead of showing them the form.
Route::inertia('/login', 'Login')->middleware('guest')->name('login');

Route::get('/community', [SavedDrawingController::class, 'community'])->name('community');

// Drawing a level in the browser instead of photographing paper. The palette
// is fixed, so a drawn level skips colour picking and starts straight away.
Route::get('/draw', DrawnLevelController::class)->name('draw');

// The colour-picking step. A fresh level is held by the browser and never
// posted here; only replaying a drawing saved before the settings columns
// existed still arrives with an image the server has to serve.
Route::get('/game-setting', [GameSettingController::class, 'show'])->name('game-setting');
Route::get('/play/{drawing}', [SavedDrawingController::class, 'play'])->name('play');
Route::get('/game', GameController::class)->name('game');

// Saved level images live on the private disk and are only served through this
// route, which checks who may see what. A level that has not been saved has no
// URL at all: it never leaves the browser.
Route::get('/drawings/{drawing}/image', LevelImageController::class)->name('drawings.image');

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

Route::post('/start-game', [GameSettingController::class, 'store'])->name('start-game');
// Throttled like the login form: registering is free and signs you straight
// in, so without a limit accounts can be created in bulk by a script. The third
// parameter names the bucket — without it, every unnamed throttle on the site
// shares one counter per visitor.
Route::post('/register', RegisteredUserController::class)
    ->middleware('throttle:5,1,registration')
    ->name('register');
Route::post('/login', LoginController::class)->name('login.attempt');
Route::post('/logout', LogoutController::class)->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/account', [SavedDrawingController::class, 'index'])->name('account');

    // Managing the account itself, from the same page. Deleting keeps whatever
    // it published — those levels are already out in the community — and takes
    // the unpublished drafts with it.
    Route::patch('/account/username', [AccountController::class, 'updateUsername'])->name('account.username');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    // The only route that accepts a level image, and so the only one worth
    // throttling for size: each accepted file is up to 10 MB of disk.
    Route::post('/save-drawing', [SavedDrawingController::class, 'store'])
        ->middleware('throttle:20,1,uploads')
        ->name('drawings.store');

    // Route model binding, like /play: an id that is not a number is a 404
    // rather than a TypeError on the controller's int parameter.
    //
    // Two routes rather than one toggle: publishing carries a title and a
    // description, unpublishing carries nothing, and one endpoint doing both
    // would have to guess which it was being asked for.
    Route::post('/drawing/{drawing}/publish', [SavedDrawingController::class, 'publish'])->name('drawings.publish');
    Route::post('/drawing/{drawing}/unpublish', [SavedDrawingController::class, 'unpublish'])->name('drawings.unpublish');

    // Liking or disliking a published level, from the game page while playing
    // it. Signing in is what makes one vote per person enforceable.
    Route::post('/drawing/{drawing}/vote', DrawingVoteController::class)->name('drawings.vote');

    Route::delete('/drawing/{drawing}', [SavedDrawingController::class, 'destroy'])->name('drawings.destroy');
});
