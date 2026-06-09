<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\UploadLevelController;
use App\Http\Controllers\GameSettingController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SavedDrawingController;



Route::get('/upload', function () {
    return view('upload');
});
Route::get('/', function () {
    return view('home');
});

Route::get('/about', function (){
    return view('about');
});

Route::get('login', function (){
    return view('login');
})->name('login');
Route::get('/community', [SavedDrawingController::class, 'community']);

Route::post('/upload-level', [UploadLevelController::class, 'uploadLevel']);
Route::post('/start-game', [GameSettingController::class, 'startGame']);
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [LoginController::class, 'store']);
Route::post('/save-drawing', [SavedDrawingController::class, 'store']);
Route::post('/logout', function() {
    Auth::logout();
    return redirect('/');
});
Route::post('/drawing/{id}/publish', [SavedDrawingController::class, 'togglePublish']);


Route::get('/account', [SavedDrawingController::class, 'index']);
Route::get('/play/{id}', function($id) {
    $drawing = App\Models\SavedDrawing::findOrFail($id);
    session(['uploadedLevel' => $drawing->image_path]);
    return view('gameSetting');
});

Route::get('/game', function(){return view('game');});
Route::delete('/drawing/{id}', [SavedDrawingController::class, 'destroy']);

Route::middleware('auth')->group(function () {
    Route::get('/account', [SavedDrawingController::class, 'index']);
    Route::post('/save-drawing', [SavedDrawingController::class, 'store']);
});