<?php

namespace Tests\Feature;

use App\Models\DrawingVote;
use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_requires_a_title()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish", ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->assertFalse($drawing->fresh()->published);
    }

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

    public function test_publishing_works_without_a_description()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish", ['title' => 'Short and sweet'])
            ->assertSessionHas('message', 'Drawing published.');

        $this->assertNull($drawing->fresh()->description);
    }

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

    public function test_voting_the_same_way_twice_withdraws_the_vote()
    {
        $drawing = $this->publishedDrawing();
        $voter = User::factory()->create();

        $this->actingAs($voter)->post("/drawing/{$drawing->id}/vote", ['value' => 1]);
        $this->actingAs($voter)->post("/drawing/{$drawing->id}/vote", ['value' => 1]);

        $this->assertSame(0, DrawingVote::count());
    }

    public function test_voting_the_other_way_replaces_the_vote()
    {
        $drawing = $this->publishedDrawing();
        $voter = User::factory()->create();

        $this->actingAs($voter)->post("/drawing/{$drawing->id}/vote", ['value' => 1]);
        $this->actingAs($voter)->post("/drawing/{$drawing->id}/vote", ['value' => -1]);

        $this->assertSame(1, DrawingVote::count());
        $this->assertSame(-1, DrawingVote::firstOrFail()->value);
    }

    public function test_you_cannot_vote_on_your_own_drawing()
    {
        $author = User::factory()->create();
        $drawing = SavedDrawing::factory()->published()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->post("/drawing/{$drawing->id}/vote", ['value' => 1])
            ->assertForbidden();

        $this->assertSame(0, DrawingVote::count());
    }

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

    public function test_searching_does_not_reveal_unpublished_drawings()
    {
        $author = User::factory()->create();

        SavedDrawing::factory()->create(['user_id' => $author->id, 'title' => 'Secret Cave']);

        $this->get('/community?search=Cave')->assertInertia(
            fn (AssertableInertia $page) => $page->has('drawings.data', 0)->etc()
        );
    }

    public function test_most_liked_ranks_by_likes_minus_dislikes()
    {
        $author = User::factory()->create();

        $steady = SavedDrawing::factory()->published()->create(['user_id' => $author->id, 'title' => 'Steady']);
        $divisive = SavedDrawing::factory()->published()->create(['user_id' => $author->id, 'title' => 'Divisive']);
        SavedDrawing::factory()->published()->create(['user_id' => $author->id, 'title' => 'Unrated']);

        $this->castVotes($steady, 1, 3);
        $this->castVotes($divisive, 1, 5);
        $this->castVotes($divisive, -1, 4);

        $this->get('/community?sort=liked')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('drawings.data.0.title', 'Steady')
            ->where('drawings.data.1.title', 'Divisive')
            ->where('drawings.data.2.title', 'Unrated')
            ->where('drawings.data.1.likes', 5)
            ->where('drawings.data.1.dislikes', 4)
            ->etc()
        );
    }

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
