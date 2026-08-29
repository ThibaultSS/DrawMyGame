<?php

namespace Tests\Feature;

use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Managing the account itself: the name other people see, the password, and
 * getting rid of the whole thing.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    /* -------------------------------------------------------------- *
     * Username
     * -------------------------------------------------------------- */

    public function test_a_user_can_change_their_username()
    {
        $user = User::factory()->create(['username' => 'oldname']);

        $this->actingAs($user)
            ->patch('/account/username', ['username' => 'newname'])
            ->assertSessionHas('message', 'Username updated.');

        $this->assertSame('newname', $user->fresh()->username);
    }

    public function test_a_username_that_is_taken_is_refused()
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create(['username' => 'mine']);

        $this->actingAs($user)
            ->patch('/account/username', ['username' => 'taken'])
            ->assertSessionHasErrors('username');

        $this->assertSame('mine', $user->fresh()->username);
    }

    // Your own name is not a collision with itself — without ignore() on the
    // unique rule, saving the form unchanged would be refused as taken
    public function test_saving_your_own_username_unchanged_is_allowed()
    {
        $user = User::factory()->create(['username' => 'mine']);

        $this->actingAs($user)
            ->patch('/account/username', ['username' => 'mine'])
            ->assertSessionHasNoErrors();
    }

    // It is the name on every community card, so it follows the level
    public function test_the_new_username_shows_on_their_community_levels()
    {
        $user = User::factory()->create(['username' => 'oldname']);
        SavedDrawing::factory()->published()->create(['user_id' => $user->id]);

        $this->actingAs($user)->patch('/account/username', ['username' => 'newname']);

        $this->get('/community')->assertInertia(
            fn (AssertableInertia $page) => $page->where('drawings.data.0.author', 'newname')->etc()
        );
    }

    /**
     * A username prints on every gallery card and every leaderboard row, so its
     * shape is everyone's problem, not just its owner's. It used to be 255
     * characters of anything at all.
     */
    public function test_a_username_must_look_like_a_username()
    {
        $user = User::factory()->create(['username' => 'mine']);

        $refused = [
            'ab',                          // under the minimum
            str_repeat('a', 31),           // over the maximum
            'has a space',
            'has.a.dot',
            'ünïcödé',
        ];

        foreach ($refused as $username) {
            $this->actingAs($user)
                ->patch('/account/username', ['username' => $username])
                ->assertSessionHasErrors('username');
        }

        $this->assertSame('mine', $user->fresh()->username);
    }

    public function test_letters_digits_hyphens_and_underscores_are_allowed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/account/username', ['username' => 'Sam_the-Great99'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Sam_the-Great99', $user->fresh()->username);
    }

    public function test_registering_applies_the_same_rules()
    {
        $this->post('/register', [
            'username' => 'no good',
            'email' => 'new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    /* -------------------------------------------------------------- *
     * Password
     * -------------------------------------------------------------- */

    public function test_a_user_can_change_their_password()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/account/password', [
                'current_password' => 'password',
                'password' => 'BrandNewPassword1!',
                'password_confirmation' => 'BrandNewPassword1!',
            ])
            ->assertSessionHas('message', 'Password updated.');

        $this->assertTrue(Hash::check('BrandNewPassword1!', $user->fresh()->password));
    }

    // A borrowed, still-signed-in browser should not be able to lock the owner
    // out of their own account
    public function test_changing_the_password_needs_the_current_one()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/account/password', [
                'current_password' => 'not-the-password',
                'password' => 'BrandNewPassword1!',
                'password_confirmation' => 'BrandNewPassword1!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/account/password', [
                'current_password' => 'password',
                'password' => 'BrandNewPassword1!',
                'password_confirmation' => 'something-else',
            ])
            ->assertSessionHasErrors('password');
    }

    /* -------------------------------------------------------------- *
     * Deleting the account
     * -------------------------------------------------------------- */

    // Confirmed by typing the username, not the password: people who signed in
    // with Google never set one
    public function test_deleting_an_account_needs_the_username_typed_exactly()
    {
        $user = User::factory()->create(['username' => 'goodbye']);

        $this->actingAs($user)
            ->delete('/account', ['username' => 'Goodbye'])
            ->assertSessionHasErrors('username');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_a_user_can_delete_their_account()
    {
        $user = User::factory()->create(['username' => 'goodbye']);

        $this->actingAs($user)->delete('/account', ['username' => 'goodbye']);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // A published level is already out in the community — other people have
    // played it and voted on it — so it stays. A private draft does not.
    public function test_deleting_an_account_keeps_published_levels_and_removes_drafts()
    {
        Storage::fake('local');

        $user = User::factory()->create(['username' => 'goodbye']);

        $published = SavedDrawing::factory()->published()->create([
            'user_id' => $user->id,
            'image_path' => 'levels/published.png',
        ]);
        $draft = SavedDrawing::factory()->create([
            'user_id' => $user->id,
            'image_path' => 'levels/draft.png',
        ]);

        Storage::disk('local')->put('levels/published.png', 'image-bytes');
        Storage::disk('local')->put('levels/draft.png', 'image-bytes');

        $this->actingAs($user)->delete('/account', ['username' => 'goodbye']);

        $this->assertDatabaseHas('saved_drawings', ['id' => $published->id, 'user_id' => null]);
        Storage::disk('local')->assertExists('levels/published.png');

        $this->assertDatabaseMissing('saved_drawings', ['id' => $draft->id]);
        Storage::disk('local')->assertMissing('levels/draft.png');
    }

    public function test_the_community_credits_a_level_with_no_author_to_an_unknown_publisher()
    {
        $drawing = SavedDrawing::factory()->published()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Left behind',
        ]);

        $drawing->update(['user_id' => null]);

        $this->get('/community')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('drawings.data.0.title', 'Left behind')
            ->where('drawings.data.0.author', 'Unknown publisher')
            ->etc()
        );
    }

    /**
     * Deleting an account removes its drafts, so an unpublished level with no
     * author should not exist — but if one ever did, a signed-out visitor's id
     * is null and so is its owner, and without an explicit check the two would
     * match and hand over a private level.
     */
    public function test_an_unpublished_level_with_no_author_stays_hidden_from_guests()
    {
        Storage::fake('local');
        Storage::disk('local')->put('levels/orphan.png', 'image-bytes');

        $drawing = SavedDrawing::factory()->create([
            'user_id' => User::factory()->create()->id,
            'image_path' => 'levels/orphan.png',
        ]);

        $drawing->update(['user_id' => null]);

        $this->get("/drawings/{$drawing->id}/image")->assertNotFound();
        $this->get("/play/{$drawing->id}")->assertNotFound();
    }
}
