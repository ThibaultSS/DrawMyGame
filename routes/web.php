<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;


Route::get('/', function () {
    return view('welcome');
});
Route::get('/upload', function () {
    return view('upload');
});

Route::post('/upload-level', function(Request $request){

    $path = $request->file('levelImage')->store('levels','public');
    session(['uploadedLevel'=>$path]);
    return redirect('/game');

});
Route::get('/game', function(){

    return view('game');

});
