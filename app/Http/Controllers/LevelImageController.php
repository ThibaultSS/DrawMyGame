<?php

namespace App\Http\Controllers;

use App\Models\SavedDrawing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LevelImageController extends Controller
{
    public function __invoke(SavedDrawing $drawing): StreamedResponse
    {
        abort_unless($drawing->isPlayableBy(Auth::id()), 404);
        abort_unless(Storage::disk('local')->exists($drawing->image_path), 404);

        return Storage::disk('local')->response($drawing->image_path);
    }
}
