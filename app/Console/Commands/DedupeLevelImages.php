<?php

namespace App\Console\Commands;

use App\Models\SavedDrawing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * A one-off for the duplicates already on disk.
 *
 * Saving someone else's level used to copy its image, so one picture saved by
 * fifty people became fifty identical files. Saving now stores an image under
 * the hash of its own contents and several drawings share it, but the files
 * written under the old rule are still there.
 *
 * This finds them, points every drawing at one copy of each picture, and
 * deletes the rest.
 */
#[Signature('levels:dedupe {--dry-run : Report what would change without touching anything}')]
#[Description('Collapse duplicate level images into one file per picture')]
class DedupeLevelImages extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $dryRun = (bool) $this->option('dry-run');

        // Group every file by the hash of its contents. Two files with the same
        // hash are the same picture, whatever they happen to be named.
        $byHash = [];

        foreach ($disk->files('levels') as $path) {
            $byHash[hash('sha256', $disk->get($path))][] = $path;
        }

        $removed = 0;
        $pictures = 0;

        foreach ($byHash as $hash => $paths) {
            $pictures++;

            $extension = pathinfo($paths[0], PATHINFO_EXTENSION);
            $canonical = "levels/{$hash}.{$extension}";

            // Nothing to collapse, and it is already under its content name.
            if (count($paths) === 1 && $paths[0] === $canonical) {
                continue;
            }

            $this->line("{$hash}: ".count($paths).' file(s) -> '.$canonical);

            if ($dryRun) {
                // One file per picture survives — renamed to the content name
                // if it is not already called that. Counting the whole group
                // would report the survivor as a deletion.
                $removed += count($paths) - 1;

                continue;
            }

            // Move one member to the content name first. Renaming even a lone
            // file matters: left under its old random name it would never match
            // a future upload of the same picture, and the duplication this
            // command exists to undo would simply start again.
            //
            // $moved is remembered rather than edited into $paths, because the
            // rows are matched on the names they still hold — including the one
            // just moved out from under them.
            $moved = null;

            if (! in_array($canonical, $paths, true)) {
                $disk->move($paths[0], $canonical);
                $moved = $paths[0];
            }

            // withTrashed, or a soft-deleted row would be left naming a file
            // about to be deleted — which is exactly what the still-referenced
            // guard and levels:prune both read.
            SavedDrawing::withTrashed()
                ->whereIn('image_path', $paths)
                ->update(['image_path' => $canonical]);

            // Only now, with every row repointed, are the extras safe to drop:
            // dying halfway through leaves rows naming a file that still exists
            // rather than one that does not.
            foreach ($paths as $duplicate) {
                if ($duplicate === $canonical || $duplicate === $moved) {
                    continue;
                }

                $disk->delete($duplicate);
                $removed++;
            }
        }

        $this->info($dryRun
            ? "Would remove {$removed} duplicate file(s) across {$pictures} distinct picture(s)."
            : "Removed {$removed} duplicate file(s) across {$pictures} distinct picture(s).");

        return self::SUCCESS;
    }
}
