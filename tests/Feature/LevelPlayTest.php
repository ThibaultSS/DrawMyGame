<?php

namespace Tests\Feature;

use App\Models\LevelPlay;
use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LevelPlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_level_records_an_attempt()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/attempt");

        $this->assertDatabaseHas('level_plays', [
            'user_id' => $user->id,
            'saved_drawing_id' => $drawing->id,
            'attempts' => 1,
            'best_time_ms' => null,
        ]);
    }

    public function test_trying_again_increments_the_same_row()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/attempt");
        $this->actingAs($user)->post("/drawing/{$drawing->id}/attempt");
        $this->actingAs($user)->post("/drawing/{$drawing->id}/attempt");

        $this->assertSame(1, LevelPlay::count());
        $this->assertSame(3, LevelPlay::first()->attempts);
    }

    public function test_finishing_a_level_records_the_time()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/completed", ['timeMs' => 42_000]);

        $play = LevelPlay::firstOrFail();

        $this->assertSame(42_000, $play->best_time_ms);
        $this->assertNotNull($play->completed_at);
    }

    public function test_a_faster_run_replaces_the_best_time()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/completed", ['timeMs' => 42_000]);
        $this->actingAs($user)->post("/drawing/{$drawing->id}/completed", ['timeMs' => 30_000]);

        $this->assertSame(30_000, LevelPlay::firstOrFail()->best_time_ms);
    }

    public function test_a_slower_run_leaves_the_best_time_alone()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/completed", ['timeMs' => 30_000]);
        $this->actingAs($user)->post("/drawing/{$drawing->id}/completed", ['timeMs' => 55_000]);

        $this->assertSame(30_000, LevelPlay::firstOrFail()->best_time_ms);
    }

    public function test_an_impossible_time_is_rejected()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/completed", ['timeMs' => 5])
            ->assertSessionHasErrors('timeMs');

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/completed", ['timeMs' => 999_999_999])
            ->assertSessionHasErrors('timeMs');

        $this->assertSame(0, LevelPlay::count());
    }

    public function test_recording_a_play_needs_an_account()
    {
        $drawing = $this->publishedLevel();

        $this->post("/drawing/{$drawing->id}/attempt")->assertRedirect('/login');
    }

    public function test_an_unplayable_level_cannot_be_recorded_against()
    {
        $drawing = SavedDrawing::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->post("/drawing/{$drawing->id}/attempt")
            ->assertNotFound();
    }

    public function test_the_game_page_reports_who_beat_it_out_of_who_tried()
    {
        $drawing = $this->publishedLevel();

        $winner = User::factory()->create(['username' => 'ana']);
        $this->actingAs($winner)->post("/drawing/{$drawing->id}/attempt");
        $this->actingAs($winner)->post("/drawing/{$drawing->id}/completed", ['timeMs' => 20_000]);

        foreach ([User::factory()->create(), User::factory()->create()] as $loser) {
            $this->actingAs($loser)->post("/drawing/{$drawing->id}/attempt");
        }

        $this->actingAs($winner)->get("/play/{$drawing->id}");

        $this->actingAs($winner)->get('/game')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('beaten', 1)
            ->where('attempted', 3)
            ->where('myBestMs', 20_000)
            ->etc()
        );
    }

    public function test_the_fastest_runs_are_listed_in_order()
    {
        $drawing = $this->publishedLevel();

        foreach ([['slow', 50_000], ['quick', 10_000], ['middling', 30_000]] as [$name, $time]) {
            $player = User::factory()->create(['username' => $name]);
            $this->actingAs($player)->post("/drawing/{$drawing->id}/completed", ['timeMs' => $time]);
        }

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get("/play/{$drawing->id}");

        $this->actingAs($viewer)->get('/game')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('fastest.0.username', 'quick')
            ->where('fastest.1.username', 'middling')
            ->where('fastest.2.username', 'slow')
            ->where('fastest.0.ms', 10_000)
            ->etc()
        );
    }

    public function test_the_community_page_shows_how_many_beat_each_level()
    {
        $drawing = $this->publishedLevel(['title' => 'Castle']);

        $winner = User::factory()->create();
        $this->actingAs($winner)->post("/drawing/{$drawing->id}/completed", ['timeMs' => 20_000]);
        $this->actingAs(User::factory()->create())->post("/drawing/{$drawing->id}/attempt");

        $this->get('/community')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('drawings.data.0.beaten', 1)
            ->where('drawings.data.0.attempted', 2)
            ->etc()
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedLevel(array $attributes = []): SavedDrawing
    {
        return SavedDrawing::factory()->published()->create([
            'user_id' => User::factory()->create()->id,
            'platform_color' => '#ff0000',
            'goal_color' => '#00ff00',
            'player_color' => '#0000ff',
            'hazard_color' => '#000000',
            ...$attributes,
        ]);
    }
}
