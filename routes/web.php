<?php

use App\Http\Controllers\GameSettingController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\SavedDrawingController;
use App\Http\Controllers\UploadLevelController;
use App\Models\SavedDrawing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/upload', function () {
    return view('upload');
});
Route::get('/', function () {
    return view('home');
});

Route::get('/about', function () {
    return view('about');
});

// The first page rebuilt as a Vue component. The rest still return Blade views.
// 'guest' sends anyone already signed in away instead of showing them the form.
Route::get('login', function () {
    return Inertia::render('Login');
})->middleware('guest')->name('login');

Route::get('/game', function () {
    return view('game');
});

Route::get('/community', [SavedDrawingController::class, 'community']);
Route::get('/account', [SavedDrawingController::class, 'index']);
Route::get('/play/{id}', function ($id) {
    $drawing = SavedDrawing::findOrFail($id);
    session(['uploadedLevel' => $drawing->image_path]);

    return view('gameSetting');
});
Route::get('/auth/google', [GoogleController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

Route::post('/upload-level', UploadLevelController::class);
Route::post('/start-game', GameSettingController::class);
Route::post('/register', RegisteredUserController::class);
Route::post('/login', LoginController::class);
Route::post('/logout', function (Request $request) {
    Auth::logout();

    // Logging out has to throw the session away, not just forget who owns it.
    // Without invalidate() the session record survives, and without
    // regenerateToken() the old CSRF token stays valid.
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    // Works both ways: an ordinary redirect for the Blade form on the account
    // page, a full page visit for an Inertia request.
    return Inertia::location('/');
});

Route::middleware('auth')->group(function () {
    Route::get('/account', [SavedDrawingController::class, 'index']);
    Route::post('/save-drawing', [SavedDrawingController::class, 'store']);
    Route::post('/drawing/{id}/publish', [SavedDrawingController::class, 'togglePublish']);
    Route::delete('/drawing/{id}', [SavedDrawingController::class, 'destroy']);
});
