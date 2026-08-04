<?php

namespace App\Http\Controllers;

use App\Models\SavedDrawing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Level images live on the private disk, so every view of one passes through
 * here. On the public disk anyone could open any upload by URL — including
 * drawings that were never published.
 */
class LevelImageController extends Controller
{
    /**
     * The image the current session is working with: a fresh upload, or a
     * drawing put there by /play — which already checked it may be played.
     * Possession of the session is the authorisation.
     */
    public function current(): StreamedResponse
    {
        $path = session('uploadedLevel');

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    /**
     * A saved drawing's image. Published drawings are visible to everyone;
     * unpublished ones only to their owner. 404 rather than 403, so an id
     * cannot be probed for existence.
     */
    public function drawing(SavedDrawing $drawing): StreamedResponse
    {
        abort_unless($drawing->published || $drawing->user_id === Auth::id(), 404);
        abort_unless(Storage::disk('local')->exists($drawing->image_path), 404);

        return Storage::disk('local')->response($drawing->image_path);
    }
}
