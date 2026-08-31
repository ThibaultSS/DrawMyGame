<?php

namespace Tests\Feature;

use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RandomLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_you_to_a_published_level()
    {
        $drawing = SavedDrawing::factory()->published()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->get('/random-level')->assertRedirect("/play/{$drawing->id}");
    }

    public function test_it_never_sends_you_to_an_unpublished_level()
    {
        $author = User::factory()->create();

        $published = SavedDrawing::factory()->published()->create(['user_id' => $author->id]);
        SavedDrawing::factory()->count(5)->create(['user_id' => $author->id]);

        foreach (range(1, 10) as $ignored) {
            $this->get('/random-level')->assertRedirect("/play/{$published->id}");
        }
    }

    public function test_it_says_so_when_nothing_is_published()
    {
        SavedDrawing::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->from('/community')
            ->get('/random-level')
            ->assertRedirect('/community')
            ->assertSessionHas('message', 'There are no published levels yet. Be the first.');
    }

    public function test_it_can_reach_more_than_one_level()
    {
        $author = User::factory()->create();
        SavedDrawing::factory()->count(4)->published()->create(['user_id' => $author->id]);

        $seen = [];

        foreach (range(1, 40) as $ignored) {
            $seen[$this->get('/random-level')->headers->get('Location')] = true;
        }

        $this->assertGreaterThan(1, count($seen));
    }

    public function test_it_does_not_need_an_account()
    {
        SavedDrawing::factory()->published()->create(['user_id' => User::factory()->create()->id]);

        $this->get('/random-level')->assertRedirectContains('/play/');
    }
}
