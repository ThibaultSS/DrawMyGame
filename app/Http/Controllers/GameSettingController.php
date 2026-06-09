<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GameSettingController extends Controller
{
    public function __invoke(Request $request)
    {
        session([
            'platformColor' => $request->platformColor,
            'goalColor' => $request->goalColor,
            'playerColor' => $request->playerColor,
            'hazardColor' => $request->hazardColor
        ]);

        return redirect('/game');
    }
}