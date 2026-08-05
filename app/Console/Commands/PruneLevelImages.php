<?php

namespace App\Console\Commands;

use App\Models\SavedDrawing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Most uploads are never saved: anyone may upload a level and play it without
 * an account, and only pressing Save attaches the file to a drawing. Nothing
 * else ever removes those files, so without this they pile up on disk forever.
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
