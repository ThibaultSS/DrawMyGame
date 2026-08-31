<?php

namespace App\Console\Commands;

use App\Models\SavedDrawing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Signature('levels:prune {--hours=24 : Leave files younger than this alone}')]
#[Description('Delete uploaded level images that no saved drawing references')]
class PruneLevelImages extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = Carbon::now()->subHours((int) $this->option('hours'))->getTimestamp();

        $referenced = SavedDrawing::withTrashed()
            ->pluck('image_path')
            ->flip();

        $deleted = 0;

        foreach ($disk->files('levels') as $path) {
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
