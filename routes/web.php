<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\UploadLevelController;
use App\Http\Controllers\GameSettingController;



Route::get('/upload', function () {
    return view('upload');
});

Route::post('/upload-level', [UploadLevelController::class, 'uploadLevel']);
Route::post('/start-game', [GameSettingController::class, 'startGame']);

Route::post('/game', function(){
    return view('game');
});
