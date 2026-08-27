<?php

namespace Tests\Feature;

use App\Models\LevelFavourite;
use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Keeping somebody else's level to play again.
 *
 * This replaced copying, which made you the owner of another person's drawing
 * and let you publish it under your own name.
 */
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

        // The level is still theirs — keeping it creates no drawing at all.
        $this->assertSame(1, SavedDrawing::count());
    }

    // Pressing it again after moving the sliders keeps the new feel rather than
    // being refused as a duplicate
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

    // You cannot keep what you already own. A 403 rather than a 404: the level
    // is published, so its existence is not a secret.
    public function test_you_cannot_keep_your_own_level()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->published()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post("/drawing/{$drawing->id}/favourite")->assertForbidden();

        $this->assertSame(0, LevelFavourite::count());
    }

    // An unpublished level is its owner's business, and a 404 keeps its id from
    // being probed for existence
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

    // The point of keeping one: it opens at your feel, not its author's. This
    // is what copying the drawing into your account used to buy.
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

    // Keeping it without touching the sliders means "however the author left
    // it", not "at the defaults"
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

    // Everyone else still plays it as its author tuned it
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

    /**
     * A favourite outlives what it points at: unpublishing does not fire the
     * foreign key's cascade, so without a filter the account page would render
     * a card that 404s the moment it is clicked.
     */
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

    // Same again for a deleted one: soft deletes do not cascade either
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

    /**
     * Both lists are paginated on one page. They default to the same ?page
     * parameter, so without a separate page name for the favourites, asking for
     * page two of one silently moves the other as well.
     */
    public function test_the_two_lists_page_independently()
    {
        $user = User::factory()->create();

        // 13 of your own, so your list has a second page.
        SavedDrawing::factory()->count(13)->create(['user_id' => $user->id]);

        $kept = $this->publishedLevel();
        $this->actingAs($user)->post("/drawing/{$kept->id}/favourite");

        // Page two of your drawings holds the thirteenth; the single kept level
        // must still be on its own first page rather than paged off the end.
        $this->actingAs($user)->get('/account?page=2')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('drawings.data', 1)
            ->has('favourites.data', 1)
            ->etc()
        );
    }

    /**
     * A published level that plays straight away.
     *
     * The colours matter: without them hasGameSettings() is false, /play sends
     * you to the colour picker instead of the game, and none of the settings
     * these tests are about ever reach the session.
     *
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
