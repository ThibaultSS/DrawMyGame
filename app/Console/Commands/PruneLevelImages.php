<?php

namespace App\Console\Commands;

use App\Models\SavedDrawing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A safety net. Level images only reach the disk as part of saving a drawing,
 * which deletes the file again if the row cannot be written, so an image that
 * nothing points at should not exist. This finds the ones that do anyway —
 * after a crash between the two writes, or a database restored from a backup
 * older than the files beside it.
 */
#[Signature('levels:prune {--hours=24 : Leave files younger than this alone}')]
#[Description('Delete uploaded level images that no saved drawing references')]
class PruneLevelImages extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = Carbon::now()->subHours((int) $this->option('hours'))->getTimestamp();

        // withTrashed as a belt-and-braces measure: a trashed drawing's file is
        // normally deleted with it, and anything still named by a row — live or
        // trashed — is not an orphan.
        $referenced = SavedDrawing::withTrashed()
            ->pluck('image_path')
            ->flip();

        $deleted = 0;

        foreach ($disk->files('levels') as $path) {
            // The grace period is what makes this safe to run at any time: a
            // file uploaded seconds ago belongs to someone who is still picking
            // colours, and no drawing references it yet either.
            if ($referenced->has($path) || $disk->lastModified($path) > $cutoff) {
                continue;
            }

            $disk->delete($path);
            $deleted++;
        }

        $this->info("Pruned {$deleted} unreferenced level image(s).");

        return self::SUCCESS;
    }
}
