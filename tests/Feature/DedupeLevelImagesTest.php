<?php

namespace Tests\Feature;

use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DedupeLevelImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_changes_nothing()
    {
        Storage::fake('local');

        $drawings = $this->threeDrawingsSharingOnePicture();

        $this->artisan('levels:dedupe --dry-run')
            ->expectsOutputToContain('Would remove 2 duplicate file(s) across 1 distinct picture(s).')
            ->assertSuccessful();

        foreach ($drawings as $path => $drawing) {
            Storage::disk('local')->assertExists($path);
            $this->assertSame($path, $drawing->fresh()->image_path);
        }
    }

    public function test_duplicates_collapse_into_one_file()
    {
        Storage::fake('local');

        $drawings = $this->threeDrawingsSharingOnePicture();

        $this->artisan('levels:dedupe')->assertSuccessful();

        $files = Storage::disk('local')->files('levels');
        $this->assertCount(1, $files);
        $this->assertSame('levels/'.hash('sha256', 'same-picture').'.png', $files[0]);

        foreach ($drawings as $drawing) {
            $this->assertSame($files[0], $drawing->fresh()->image_path);
        }
    }

    public function test_trashed_drawings_are_repointed_as_well()
    {
        Storage::fake('local');

        $drawings = $this->threeDrawingsSharingOnePicture();
        $trashed = $drawings['levels/copy-one.png'];
        $trashed->delete();

        $this->artisan('levels:dedupe')->assertSuccessful();

        $expected = 'levels/'.hash('sha256', 'same-picture').'.png';

        $this->assertSame($expected, SavedDrawing::withTrashed()->find($trashed->id)->image_path);
    }

    public function test_different_pictures_are_left_alone()
    {
        Storage::fake('local');

        Storage::disk('local')->put('levels/one.png', 'first-picture');
        Storage::disk('local')->put('levels/two.png', 'second-picture');

        $this->drawingFor('levels/one.png');
        $this->drawingFor('levels/two.png');

        $this->artisan('levels:dedupe')->assertSuccessful();

        $this->assertCount(2, Storage::disk('local')->files('levels'));
    }

    public function test_a_single_file_is_renamed_to_its_content_name()
    {
        Storage::fake('local');

        Storage::disk('local')->put('levels/random-name.png', 'lonely-picture');
        $drawing = $this->drawingFor('levels/random-name.png');

        $this->artisan('levels:dedupe')->assertSuccessful();

        $expected = 'levels/'.hash('sha256', 'lonely-picture').'.png';

        Storage::disk('local')->assertExists($expected);
        Storage::disk('local')->assertMissing('levels/random-name.png');
        $this->assertSame($expected, $drawing->fresh()->image_path);
    }

    /**
     * @return array<string, SavedDrawing>
     */
    private function threeDrawingsSharingOnePicture(): array
    {
        $drawings = [];

        foreach (['levels/original.png', 'levels/copy-one.png', 'levels/copy-two.png'] as $path) {
            Storage::disk('local')->put($path, 'same-picture');

            $drawings[$path] = $this->drawingFor($path);
        }

        return $drawings;
    }

    private function drawingFor(string $path): SavedDrawing
    {
        return SavedDrawing::factory()->create([
            'user_id' => User::factory()->create()->id,
            'image_path' => $path,
        ]);
    }
}
