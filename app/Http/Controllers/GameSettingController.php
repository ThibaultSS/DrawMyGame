<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GameSettingController extends Controller
{
    public function startGame(Request $request)
    {
        session([
            'platformColor' => $request->platformColor,
            'goalColor' => $request->goalColor,
            'playerColor' => $request->playerColor
        ]);

        return redirect('/game');
    }
}