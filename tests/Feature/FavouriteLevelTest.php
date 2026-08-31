<?php

namespace Tests\Feature;

use App\Models\LevelFavourite;
use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FavouriteLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_keep_someone_elses_level()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/favourite", ['speed' => 12, 'jumpHeight' => 22])
            ->assertSessionHas('message', 'Saved to your account.');

        $this->assertDatabaseHas('level_favourites', [
            'user_id' => $user->id,
            'saved_drawing_id' => $drawing->id,
            'speed' => 12,
            'jump_height' => 22,
        ]);

        $this->assertSame(1, SavedDrawing::count());
    }

    public function test_keeping_a_level_twice_updates_it_instead_of_duplicating()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/favourite", ['speed' => 5, 'jumpHeight' => 10]);
        $this->actingAs($user)->post("/drawing/{$drawing->id}/favourite", ['speed' => 18, 'jumpHeight' => 28]);

        $this->assertSame(1, LevelFavourite::count());
        $this->assertDatabaseHas('level_favourites', ['speed' => 18, 'jump_height' => 28]);
    }

    public function test_a_user_can_stop_keeping_a_level()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/favourite");
        $this->actingAs($user)
            ->delete("/drawing/{$drawing->id}/favourite")
            ->assertSessionHas('message', 'Removed from your account.');

        $this->assertSame(0, LevelFavourite::count());
    }

    public function test_you_cannot_keep_your_own_level()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->published()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post("/drawing/{$drawing->id}/favourite")->assertForbidden();

        $this->assertSame(0, LevelFavourite::count());
    }

    public function test_an_unpublished_level_cannot_be_kept()
    {
        $drawing = SavedDrawing::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->post("/drawing/{$drawing->id}/favourite")
            ->assertNotFound();
    }

    public function test_keeping_a_level_needs_an_account()
    {
        $drawing = $this->publishedLevel();

        $this->post("/drawing/{$drawing->id}/favourite")->assertRedirect('/login');
    }

    public function test_a_kept_level_plays_at_your_own_speed_and_jump()
    {
        $drawing = $this->publishedLevel(['speed' => 3, 'jump_height' => 8]);
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/favourite", ['speed' => 17, 'jumpHeight' => 27]);

        $this->actingAs($user)
            ->get("/play/{$drawing->id}")
            ->assertSessionHas('gameSpeed', 17)
            ->assertSessionHas('jumpHeight', 27);
    }

    public function test_keeping_a_level_without_settings_leaves_the_authors_feel()
    {
        $drawing = $this->publishedLevel(['speed' => 3, 'jump_height' => 8]);
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/favourite");

        $this->actingAs($user)
            ->get("/play/{$drawing->id}")
            ->assertSessionHas('gameSpeed', 3)
            ->assertSessionHas('jumpHeight', 8);
    }

    public function test_your_settings_do_not_change_the_level_for_anyone_else()
    {
        $drawing = $this->publishedLevel(['speed' => 3, 'jump_height' => 8]);

        $this->actingAs(User::factory()->create())
            ->post("/drawing/{$drawing->id}/favourite", ['speed' => 17, 'jumpHeight' => 27]);

        $this->actingAs(User::factory()->create())
            ->get("/play/{$drawing->id}")
            ->assertSessionHas('gameSpeed', 3);
    }

    public function test_kept_levels_are_listed_on_the_account_page()
    {
        $author = User::factory()->create(['username' => 'sam']);
        $drawing = $this->publishedLevel(['user_id' => $author->id, 'title' => 'Castle']);
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/favourite");

        $this->actingAs($user)->get('/account')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('favourites.data', 1)
            ->where('favourites.data.0.title', 'Castle')
            ->where('favourites.data.0.author', 'sam')
            ->etc()
        );
    }

    public function test_a_kept_level_that_was_unpublished_drops_off_the_account_page()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/favourite");

        $drawing->update(['published' => false]);

        $this->actingAs($user)->get('/account')->assertInertia(
            fn (AssertableInertia $page) => $page->has('favourites.data', 0)->etc()
        );
    }

    public function test_a_kept_level_that_was_deleted_drops_off_the_account_page()
    {
        $drawing = $this->publishedLevel();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/drawing/{$drawing->id}/favourite");

        $drawing->delete();

        $this->actingAs($user)->get('/account')->assertInertia(
            fn (AssertableInertia $page) => $page->has('favourites.data', 0)->etc()
        );
    }

    public function test_the_two_lists_page_independently()
    {
        $user = User::factory()->create();

        SavedDrawing::factory()->count(13)->create(['user_id' => $user->id]);

        $kept = $this->publishedLevel();
        $this->actingAs($user)->post("/drawing/{$kept->id}/favourite");

        $this->actingAs($user)->get('/account?page=2')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('drawings.data', 1)
            ->has('favourites.data', 1)
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
