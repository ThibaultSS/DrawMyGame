<?php

namespace App\Console\Commands;

use App\Models\SavedDrawing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('levels:dedupe {--dry-run : Report what would change without touching anything}')]
#[Description('Collapse duplicate level images into one file per picture')]
class DedupeLevelImages extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $dryRun = (bool) $this->option('dry-run');

        $byHash = [];

        foreach ($disk->files('levels') as $path) {
            $byHash[hash_file('sha256', $disk->path($path))][] = $path;
        }

        $removed = 0;
        $pictures = 0;

        foreach ($byHash as $hash => $paths) {
            $pictures++;

            $extension = pathinfo($paths[0], PATHINFO_EXTENSION);
            $canonical = "levels/{$hash}.{$extension}";

            if (count($paths) === 1 && $paths[0] === $canonical) {
                continue;
            }

            $this->line("{$hash}: ".count($paths).' file(s) -> '.$canonical);

            if ($dryRun) {
                $removed += count($paths) - 1;

                continue;
            }

            $moved = null;

            if (! in_array($canonical, $paths, true)) {
                $disk->move($paths[0], $canonical);
                $moved = $paths[0];
            }

            SavedDrawing::withTrashed()
                ->whereIn('image_path', $paths)
                ->update(['image_path' => $canonical]);

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
