<?php

namespace App\Http\Controllers;

use App\Models\SavedDrawing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A saved drawing's image. Level images live on the private disk, so every view
 * of one passes through here — on the public disk anyone could open any saved
 * level by URL, including drawings that were never published.
 *
 * There is no route for a level being played but not yet saved: those never
 * reach the server at all, because the browser holds them until Save.
 */
class LevelImageController extends Controller
{
    /**
     * Published drawings are visible to everyone, unpublished ones only to
     * their owner. 404 rather than 403, so an id cannot be probed for
     * existence.
     */
    public function __invoke(SavedDrawing $drawing): StreamedResponse
    {
        abort_unless($drawing->isPlayableBy(Auth::id()), 404);
        abort_unless(Storage::disk('local')->exists($drawing->image_path), 404);

        return Storage::disk('local')->response($drawing->image_path);
    }
}
