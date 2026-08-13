<?php

namespace Tests\Feature;

use App\Models\DrawingVote;
use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Publishing a level with a title and description, voting on other people's,
 * and finding your way around the gallery.
 *
 * Kept apart from GameTest, which is about getting a picture into the engine
 * and back out again — a different subject and already long enough.
 */
class CommunityTest extends TestCase
{
    use RefreshDatabase;

    /* -------------------------------------------------------------- *
     * Publishing
     * -------------------------------------------------------------- */

    // 1. A card in the gallery with no title says nothing about the level, so
    // publishing without one is refused
    public function test_publishing_requires_a_title()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish", ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->assertFalse($drawing->fresh()->published);
    }

    // 1b. Not just an empty title but an empty request: the form's `required`
    // is only a courtesy to the browser, so the server has to hold on its own
    public function test_publishing_with_an_empty_body_is_rejected()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish")
            ->assertSessionHasErrors('title');

        $this->assertFalse($drawing->fresh()->published);
    }

    public function test_publishing_rejects_a_title_that_is_too_long()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish", ['title' => str_repeat('a', 81)])
            ->assertSessionHasErrors('title');
    }

    // 2. Publishing stores what the gallery will show
    public function test_publishing_stores_the_title_and_description()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish", [
                'title' => 'Cave of Doom',
                'description' => 'Mind the spikes on the second jump.',
            ])
            ->assertSessionHas('message', 'Drawing published.');

        $drawing->refresh();

        $this->assertTrue($drawing->published);
        $this->assertSame('Cave of Doom', $drawing->title);
        $this->assertSame('Mind the spikes on the second jump.', $drawing->description);
    }

    // 2b. A description is optional: some levels speak for themselves
    public function test_publishing_works_without_a_description()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish", ['title' => 'Short and sweet'])
            ->assertSessionHas('message', 'Drawing published.');

        $this->assertNull($drawing->fresh()->description);
    }

    // 3. Posting publish again is how the details are edited, and it says so
    public function test_publishing_again_edits_the_details()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->published()->create([
            'user_id' => $user->id,
            'title' => 'First name',
        ]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish", ['title' => 'Better name'])
            ->assertSessionHas('message', 'Details updated.');

        $this->assertSame('Better name', $drawing->fresh()->title);
        $this->assertTrue($drawing->fresh()->published);
    }

    // 4. Unpublishing keeps the text: taking a level out of the gallery for a
    // while should not mean writing it again to put it back
    public function test_unpublishing_keeps_the_title_and_description()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->published()->create([
            'user_id' => $user->id,
            'title' => 'Cave of Doom',
            'description' => 'Mind the spikes.',
        ]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/unpublish")
            ->assertSessionHas('message', 'Drawing unpublished.');

        $drawing->refresh();

        $this->assertFalse($drawing->published);
        $this->assertSame('Cave of Doom', $drawing->title);
    }

    /* -------------------------------------------------------------- *
     * Voting
     * -------------------------------------------------------------- */

    // 5. Voting needs an account — that is what makes one vote per person
    // something the database can enforce
    public function test_voting_requires_signing_in()
    {
        $drawing = $this->publishedDrawing();

        $this->post("/drawing/{$drawing->id}/vote", ['value' => 1])
            ->assertRedirect('/login');

        $this->assertSame(0, DrawingVote::count());
    }

    public function test_a_signed_in_visitor_can_like_a_drawing()
    {
        $drawing = $this->publishedDrawing();

        $this->actingAs(User::factory()->create())
            ->post("/drawing/{$drawing->id}/vote", ['value' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('drawing_votes', [
            'saved_drawing_id' => $drawing->id,
            'value' => 1,
        ]);
    }

    // 6. Pressing the same button again means "never mind" — there is no third
    // button for taking a vote back
    public function test_voting_the_same_way_twice_withdraws_the_vote()
    {
        $drawing = $this->publishedDrawing();
        $voter = User::factory()->create();

        $this->actingAs($voter)->post("/drawing/{$drawing->id}/vote", ['value' => 1]);
        $this->actingAs($voter)->post("/drawing/{$drawing->id}/vote", ['value' => 1]);

        $this->assertSame(0, DrawingVote::count());
    }

    // 6b. Changing your mind replaces the vote rather than adding a second one
    public function test_voting_the_other_way_replaces_the_vote()
    {
        $drawing = $this->publishedDrawing();
        $voter = User::factory()->create();

        $this->actingAs($voter)->post("/drawing/{$drawing->id}/vote", ['value' => 1]);
        $this->actingAs($voter)->post("/drawing/{$drawing->id}/vote", ['value' => -1]);

        $this->assertSame(1, DrawingVote::count());
        $this->assertSame(-1, DrawingVote::firstOrFail()->value);
    }

    // 7. Authors do not rank their own work
    public function test_you_cannot_vote_on_your_own_drawing()
    {
        $author = User::factory()->create();
        $drawing = SavedDrawing::factory()->published()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->post("/drawing/{$drawing->id}/vote", ['value' => 1])
            ->assertForbidden();

        $this->assertSame(0, DrawingVote::count());
    }

    // 7b. An unpublished level is its owner's business — 404, so the id cannot
    // be probed for existence
    public function test_you_cannot_vote_on_an_unpublished_drawing()
    {
        $drawing = SavedDrawing::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->post("/drawing/{$drawing->id}/vote", ['value' => 1])
            ->assertNotFound();
    }

    public function test_a_vote_must_be_a_like_or_a_dislike()
    {
        $drawing = $this->publishedDrawing();

        $this->actingAs(User::factory()->create())
            ->post("/drawing/{$drawing->id}/vote", ['value' => 5])
            ->assertSessionHasErrors('value');
    }

    // 8. The game page carries the standing and whether this visitor may vote
    public function test_the_game_page_carries_the_vote_state()
    {
        $drawing = $this->publishedDrawing();
        $voter = User::factory()->create();

        $this->actingAs($voter)->post("/drawing/{$drawing->id}/vote", ['value' => 1]);
        $this->actingAs($voter)->get("/play/{$drawing->id}");

        $this->actingAs($voter)->get('/game')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('likes', 1)
            ->where('dislikes', 0)
            ->where('myVote', 1)
            ->where('canVote', true)
            ->etc()
        );
    }

    public function test_the_author_is_not_offered_a_vote_on_their_own_level()
    {
        $author = User::factory()->create();
        $drawing = SavedDrawing::factory()->published()->create([
            'user_id' => $author->id,
            'platform_color' => '#ff0000',
            'goal_color' => '#00ff00',
            'player_color' => '#0000ff',
            'hazard_color' => '#000000',
        ]);

        $this->actingAs($author)->get("/play/{$drawing->id}");

        $this->actingAs($author)->get('/game')->assertInertia(
            fn (AssertableInertia $page) => $page->where('canVote', false)->etc()
        );
    }

    /* -------------------------------------------------------------- *
     * Finding a level
     * -------------------------------------------------------------- */

    public function test_the_gallery_can_be_searched_by_title()
    {
        $author = User::factory()->create();

        SavedDrawing::factory()->published()->create(['user_id' => $author->id, 'title' => 'Cave of Doom']);
        SavedDrawing::factory()->published()->create(['user_id' => $author->id, 'title' => 'Sunny Meadow']);

        $this->get('/community?search=Cave')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('drawings.data', 1)
            ->where('drawings.data.0.title', 'Cave of Doom')
            ->where('filters.search', 'Cave')
            ->etc()
        );
    }

    public function test_the_gallery_can_be_searched_by_author()
    {
        $wanted = User::factory()->create(['username' => 'pixelmaker']);
        $other = User::factory()->create(['username' => 'someoneelse']);

        SavedDrawing::factory()->published()->create(['user_id' => $wanted->id, 'title' => 'Theirs']);
        SavedDrawing::factory()->published()->create(['user_id' => $other->id, 'title' => 'Not theirs']);

        $this->get('/community?search=pixelmaker')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('drawings.data', 1)
            ->where('drawings.data.0.author', 'pixelmaker')
            ->etc()
        );
    }

    // 9. Searching must not smuggle unpublished drawings into the gallery: the
    // or-clause has to stay grouped inside the published filter
    public function test_searching_does_not_reveal_unpublished_drawings()
    {
        $author = User::factory()->create();

        SavedDrawing::factory()->create(['user_id' => $author->id, 'title' => 'Secret Cave']);

        $this->get('/community?search=Cave')->assertInertia(
            fn (AssertableInertia $page) => $page->has('drawings.data', 0)->etc()
        );
    }

    // 10. Ranked by likes minus dislikes, so a divisive level does not outrank
    // a quietly good one on raw likes alone
    public function test_most_liked_ranks_by_likes_minus_dislikes()
    {
        $author = User::factory()->create();

        $steady = SavedDrawing::factory()->published()->create(['user_id' => $author->id, 'title' => 'Steady']);
        $divisive = SavedDrawing::factory()->published()->create(['user_id' => $author->id, 'title' => 'Divisive']);
        SavedDrawing::factory()->published()->create(['user_id' => $author->id, 'title' => 'Unrated']);

        $this->castVotes($steady, 1, 3);              // score 3
        $this->castVotes($divisive, 1, 5);
        $this->castVotes($divisive, -1, 4);           // score 1, but more likes

        $this->get('/community?sort=liked')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('drawings.data.0.title', 'Steady')
            ->where('drawings.data.1.title', 'Divisive')
            ->where('drawings.data.2.title', 'Unrated')
            ->where('drawings.data.1.likes', 5)
            ->where('drawings.data.1.dislikes', 4)
            ->etc()
        );
    }

    // 11. Page two has to keep the search and the sort, or paging through a
    // search silently drops back to the whole gallery
    public function test_pagination_keeps_the_search_and_sort()
    {
        $author = User::factory()->create();

        SavedDrawing::factory()->count(13)->published()->create([
            'user_id' => $author->id,
            'title' => 'Cave level',
        ]);
        SavedDrawing::factory()->published()->create(['user_id' => $author->id, 'title' => 'Meadow']);

        $this->get('/community?search=Cave&sort=liked')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('drawings.data', 12)
            // The generated page links carry the filters forward.
            ->where('drawings.links', fn ($links) => collect($links)->contains(
                fn ($link) => $link['url'] && str_contains($link['url'], 'search=Cave')
            ))
            ->etc()
        );

        $this->get('/community?search=Cave&sort=liked&page=2')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('drawings.data', 1)
            ->where('filters.sort', 'liked')
            ->etc()
        );
    }

    /* -------------------------------------------------------------- *
     * Helpers
     * -------------------------------------------------------------- */

    /** A published level belonging to somebody else, ready to be voted on. */
    private function publishedDrawing(): SavedDrawing
    {
        return SavedDrawing::factory()->published()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Cave of Doom',
            'platform_color' => '#ff0000',
            'goal_color' => '#00ff00',
            'player_color' => '#0000ff',
            'hazard_color' => '#000000',
        ]);
    }

    /** One vote per voter, because the table only allows one. */
    private function castVotes(SavedDrawing $drawing, int $value, int $times): void
    {
        foreach (range(1, $times) as $ignored) {
            DrawingVote::create([
                'user_id' => User::factory()->create()->id,
                'saved_drawing_id' => $drawing->id,
                'value' => $value,
            ]);
        }
    }
}
