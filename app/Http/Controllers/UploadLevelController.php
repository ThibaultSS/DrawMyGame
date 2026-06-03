<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UploadLevelController extends Controller
{
    public function uploadLevel(Request $request)
    {
        $path = $request
            ->file('levelImage')
            ->store('levels','public');
        session(['uploadedLevel'=>$path]);

        return view(
            'GameSetting',
            [
                'imagePath' => $path
            ]
        );
    }
}
