<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\UploadLevelController;
use App\Http\Controllers\GameSettingController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\LoginController;



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
});

Route::post('/upload-level', [UploadLevelController::class, 'uploadLevel']);
Route::post('/start-game', [GameSettingController::class, 'startGame']);
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [LoginController::class, 'store']);

Route::get('/game', function(){return view('game');});
