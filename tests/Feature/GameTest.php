<?php

namespace Tests\Feature;

use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Home'));
    }

    public function test_upload_page_loads()
    {
        $response = $this->get('/upload');
        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Upload'));
    }

    public function test_about_page_loads()
    {
        $response = $this->get('/about');
        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('About'));
    }

    public function test_cookies_page_loads()
    {
        $response = $this->get('/cookies');
        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Cookies'));
    }

    public function test_community_page_loads()
    {
        $author = User::factory()->create();
        SavedDrawing::create([
            'user_id' => $author->id,
            'image_path' => 'levels/published.jpg',
            'published' => true,
        ]);
        SavedDrawing::create([
            'user_id' => $author->id,
            'image_path' => 'levels/private.jpg',
        ]);

        $response = $this->get('/community');
        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Community')
            ->has('drawings.data', 1)
            ->has('drawings.data.0', fn (AssertableInertia $drawing) => $drawing
                ->where('author', $author->username)
                ->hasAll(['id', 'image', 'title', 'description', 'likes', 'dislikes', 'beaten', 'attempted'])
            )
        );
    }

    public function test_account_page_redirects_when_not_logged_in()
    {
        $response = $this->get('/account');
        $response->assertRedirect('/login');
    }

    public function test_account_page_loads_when_logged_in()
    {
        $user = User::factory()->create();
        SavedDrawing::create([
            'user_id' => $user->id,
            'image_path' => 'levels/mine.jpg',
        ]);

        $response = $this->actingAs($user)->get('/account');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account')
            ->has('drawings.data', 1)
            ->has('drawings.data.0', fn (AssertableInertia $drawing) => $drawing
                ->hasAll(['id', 'image', 'published'])
                ->etc()
            )
        );
    }

    public function test_account_page_lists_only_your_own_drawings()
    {
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();

        SavedDrawing::create(['user_id' => $user->id, 'image_path' => 'levels/mine.jpg']);
        SavedDrawing::create(['user_id' => $someoneElse->id, 'image_path' => 'levels/theirs.jpg']);

        $this->actingAs($user)
            ->get('/account')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('drawings.data', 1));
    }

    public function test_user_can_publish_and_unpublish_their_own_drawing()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::create([
            'user_id' => $user->id,
            'image_path' => 'levels/mine.jpg',
        ]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish", ['title' => 'My level'])
            ->assertSessionHas('message', 'Drawing published.');

        $this->assertTrue($drawing->fresh()->published);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/unpublish")
            ->assertSessionHas('message', 'Drawing unpublished.');

        $this->assertFalse($drawing->fresh()->published);
    }

    public function test_user_can_delete_their_own_drawing()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::create([
            'user_id' => $user->id,
            'image_path' => 'levels/mine.jpg',
        ]);

        $this->actingAs($user)
            ->delete("/drawing/{$drawing->id}")
            ->assertSessionHas('message', 'Drawing deleted.');

        $this->assertSoftDeleted($drawing);
    }

    public function test_user_cannot_publish_or_delete_someone_elses_drawing()
    {
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();

        $drawing = SavedDrawing::create([
            'user_id' => $someoneElse->id,
            'image_path' => 'levels/theirs.jpg',
        ]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish", ['title' => 'Not mine'])
            ->assertNotFound();
        $this->actingAs($user)->post("/drawing/{$drawing->id}/unpublish")->assertNotFound();
        $this->actingAs($user)->delete("/drawing/{$drawing->id}")->assertNotFound();

        $this->assertFalse($drawing->fresh()->published);
        $this->assertNotSoftDeleted($drawing);
    }

    public function test_user_can_register()
    {
        $response = $this->post('/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

        $this->assertAuthenticated();
    }

    public function test_user_cannot_register_with_duplicate_email()
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', [
            'username' => 'anotheruser',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_wrong_password()
    {
        User::factory()->create([
            'email' => 'wrong@example.com',
            'password' => bcrypt('CorrectPassword123!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_page_renders_the_inertia_component()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Login'));
    }

    public function test_login_failures_do_not_reveal_whether_the_account_exists()
    {
        User::factory()->create([
            'email' => 'known@example.com',
            'password' => bcrypt('CorrectPassword123!'),
        ]);

        $message = 'Email or password is incorrect.';

        $this->post('/login', [
            'email' => 'known@example.com',
            'password' => 'WrongPassword123!',
        ])->assertSessionHasErrors(['email' => $message]);

        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'WrongPassword123!',
        ])->assertSessionHasErrors(['email' => $message]);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures()
    {
        User::factory()->create([
            'email' => 'throttled@example.com',
            'password' => bcrypt('CorrectPassword123!'),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', [
                'email' => 'throttled@example.com',
                'password' => 'WrongPassword123!',
            ])->assertSessionHasErrors(['email' => 'Email or password is incorrect.']);
        }

        $this->post('/login', [
            'email' => 'throttled@example.com',
            'password' => 'CorrectPassword123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_returns_the_user_to_the_page_they_asked_for()
    {
        $user = User::factory()->create([
            'email' => 'intended@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->get('/account')->assertRedirect('/login');

        $this->post('/login', [
            'email' => 'intended@example.com',
            'password' => 'Password123!',
        ])->assertRedirect('/account');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_page_redirects_a_user_who_is_already_signed_in()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect('/');
    }

    public function test_logout_invalidates_the_session()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_registration_rejects_a_password_that_is_too_short()
    {
        $this->post('/register', [
            'username' => 'shortpass',
            'email' => 'short@example.com',
            'password' => 'a',
            'password_confirmation' => 'a',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'short@example.com']);
        $this->assertGuest();
    }

    public function test_user_can_save_drawing()
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                'platformColor' => '#ff0000',
                'goalColor' => '#00ff00',
                'playerColor' => '#0000ff',
                'hazardColor' => '#000000',
            ])
            ->post('/save-drawing', [
                'levelImage' => UploadedFile::fake()->image('level.png'),
                'speed' => 12,
                'jumpHeight' => 22,
            ])
            ->assertSessionHas('message', 'Drawing saved.');

        $this->assertDatabaseHas('saved_drawings', [
            'user_id' => $user->id,
            'platform_color' => '#ff0000',
            'goal_color' => '#00ff00',
            'player_color' => '#0000ff',
            'hazard_color' => '#000000',
            'speed' => 12,
            'jump_height' => 22,
        ]);

        Storage::disk('local')->assertExists(SavedDrawing::firstOrFail()->image_path);
    }

    public function test_saving_rejects_out_of_range_settings()
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())
            ->post('/save-drawing', [
                'levelImage' => UploadedFile::fake()->image('level.png'),
                'speed' => 99,
                'jumpHeight' => 22,
            ])
            ->assertSessionHasErrors('speed');
    }

    public function test_a_level_is_not_stored_until_it_is_saved()
    {
        Storage::fake('local');

        $this->post('/start-game', [
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#000000',
        ])->assertRedirect('/game');

        $this->get('/game')->assertOk();

        $this->assertCount(0, Storage::disk('local')->files('levels'));
    }

    public function test_saving_nothing_is_rejected()
    {
        $this->actingAs(User::factory()->create())
            ->post('/save-drawing', ['speed' => 5, 'jumpHeight' => 10])
            ->assertSessionHasErrors('levelImage');
    }

    public function test_saving_a_non_image_is_rejected()
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())
            ->post('/save-drawing', [
                'levelImage' => UploadedFile::fake()->create('level.pdf', 100, 'application/pdf'),
                'speed' => 5,
                'jumpHeight' => 10,
            ])
            ->assertSessionHasErrors('levelImage');
    }

    public function test_saving_an_oversized_image_is_rejected()
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())
            ->post('/save-drawing', [
                'levelImage' => UploadedFile::fake()->image('level.png')->size(10 * 1024 + 1),
                'speed' => 5,
                'jumpHeight' => 10,
            ])
            ->assertSessionHasErrors('levelImage');
    }

    public function test_game_setting_page_has_no_image_for_a_browser_held_level()
    {
        $this->get('/game-setting')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('GameSetting')
            ->where('image', null)
        );
    }

    public function test_game_setting_page_shows_a_replayed_drawings_image()
    {
        $legacy = SavedDrawing::factory()->published()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->get("/play/{$legacy->id}")->assertRedirect('/game-setting');

        $this->get('/game-setting')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('GameSetting')
            ->where('image', route('drawings.image', $legacy))
        );
    }

    public function test_start_game_saves_the_colours_and_redirects()
    {
        $response = $this->post('/start-game', [
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#000000',
        ]);

        $response->assertRedirect('/game');
        $response->assertSessionHas('platformColor', '#ff0000');
        $response->assertSessionHas('goalColor', '#00ff00');
        $response->assertSessionHas('playerColor', '#0000ff');
        $response->assertSessionHas('hazardColor', '#000000');
    }

    public function test_start_game_requires_the_three_essential_colours()
    {
        $this->post('/start-game')->assertSessionHasErrors([
            'platformColor',
            'goalColor',
            'playerColor',
        ]);
    }

    public function test_start_game_works_without_a_hazard()
    {
        $this->post('/start-game', [
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
        ])->assertRedirect('/game');

        $this->get('/game')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Game')
            ->where('hazardColor', null)
            ->etc()
        );
    }

    public function test_starting_a_game_without_a_hazard_clears_the_previous_one()
    {
        $this->withSession(['hazardColor' => '#ff0000'])
            ->post('/start-game', [
                'platformColor' => '#ff0000',
                'goalColor' => '#00ff00',
                'playerColor' => '#0000ff',
            ])
            ->assertRedirect('/game')
            ->assertSessionMissing('hazardColor');
    }

    public function test_game_page_renders_with_the_colours_and_no_server_image()
    {
        $response = $this->withSession([
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#000000',
        ])->get('/game');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Game')
            ->where('levelImage', null)
            ->where('drawingId', null)
            ->where('platformColor', '#ff0000')
            ->where('goalColor', '#00ff00')
            ->where('playerColor', '#0000ff')
            ->where('hazardColor', '#000000')
        );
    }

    public function test_game_page_serves_a_replayed_drawings_image()
    {
        $drawing = SavedDrawing::factory()->published()->create([
            'user_id' => User::factory()->create()->id,
            'platform_color' => '#ff0000',
            'goal_color' => '#00ff00',
            'player_color' => '#0000ff',
            'hazard_color' => '#000000',
        ]);

        $this->get("/play/{$drawing->id}")->assertRedirect('/game');

        $this->get('/game')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Game')
            ->where('levelImage', route('drawings.image', $drawing))
            ->where('drawingId', $drawing->id)
            ->etc()
        );
    }

    public function test_game_page_requires_the_full_session()
    {
        $this->get('/game')->assertRedirect('/upload');

        $this->withSession(['platformColor' => '#ff0000'])
            ->get('/game')
            ->assertRedirect('/upload');
    }

    public function test_play_puts_the_drawing_in_the_session_and_redirects()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::create([
            'user_id' => $user->id,
            'image_path' => 'levels/mine.jpg',
            'published' => true,
        ]);

        $response = $this->get("/play/{$drawing->id}");

        $response->assertRedirect('/game-setting');
        $response->assertSessionHas('replayDrawingId', $drawing->id);
    }

    public function test_play_starts_immediately_when_the_drawing_has_settings()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->published()->create([
            'user_id' => $user->id,
            'platform_color' => '#ff0000',
            'goal_color' => '#00ff00',
            'player_color' => '#0000ff',
            'hazard_color' => '#000000',
            'speed' => 15,
            'jump_height' => 25,
        ]);

        $response = $this->get("/play/{$drawing->id}");

        $response->assertRedirect('/game');
        $response->assertSessionHas('replayDrawingId', $drawing->id);
        $response->assertSessionHas('platformColor', '#ff0000');
        $response->assertSessionHas('gameSpeed', 15);
        $response->assertSessionHas('jumpHeight', 25);
    }

    public function test_game_page_passes_the_saved_speed_and_jump()
    {
        $this->withSession([
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#000000',
            'gameSpeed' => 15,
            'jumpHeight' => 25,
        ])->get('/game')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Game')
            ->where('speed', 15)
            ->where('jumpHeight', 25)
            ->etc()
        );
    }

    public function test_start_game_resets_the_saved_speed_and_jump()
    {
        $this->withSession(['gameSpeed' => 15, 'jumpHeight' => 25])
            ->post('/start-game', [
                'platformColor' => '#ff0000',
                'goalColor' => '#00ff00',
                'playerColor' => '#0000ff',
                'hazardColor' => '#000000',
            ])
            ->assertRedirect('/game')
            ->assertSessionMissing('gameSpeed')
            ->assertSessionMissing('jumpHeight');
    }

    public function test_play_without_settings_clears_the_previous_games_session()
    {
        $user = User::factory()->create();
        $legacy = SavedDrawing::factory()->published()->create(['user_id' => $user->id]);

        $this->withSession([
            'replayDrawingId' => 999,
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#000000',
            'gameSpeed' => 15,
            'jumpHeight' => 25,
        ])->get("/play/{$legacy->id}")
            ->assertRedirect('/game-setting')
            ->assertSessionHas('replayDrawingId', $legacy->id)
            ->assertSessionMissing('platformColor')
            ->assertSessionMissing('gameSpeed')
            ->assertSessionMissing('jumpHeight');
    }

    public function test_starting_a_fresh_game_stops_replaying_the_previous_drawing()
    {
        $this->withSession(['replayDrawingId' => 42])
            ->post('/start-game', [
                'platformColor' => '#ff0000',
                'goalColor' => '#00ff00',
                'playerColor' => '#0000ff',
                'hazardColor' => '#000000',
            ])
            ->assertRedirect('/game')
            ->assertSessionMissing('replayDrawingId');
    }

    public function test_resaving_your_own_drawing_updates_it_in_place()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $drawing = SavedDrawing::factory()->create([
            'user_id' => $user->id,
            'platform_color' => '#ff0000',
            'goal_color' => '#00ff00',
            'player_color' => '#0000ff',
            'hazard_color' => '#000000',
            'speed' => 5,
            'jump_height' => 10,
        ]);
        Storage::disk('local')->put($drawing->image_path, 'image-bytes');

        $this->actingAs($user)->get("/play/{$drawing->id}");
        $this->actingAs($user)
            ->post('/save-drawing', [
                'drawingId' => $drawing->id,
                'speed' => 18,
                'jumpHeight' => 28,
            ])
            ->assertSessionHas('message', 'Drawing updated.');

        $this->assertSame(1, SavedDrawing::count());
        $this->assertSame(18, $drawing->fresh()->speed);
        $this->assertSame(28, $drawing->fresh()->jump_height);
        $this->assertCount(1, Storage::disk('local')->files('levels'));
    }

    public function test_a_drawing_without_a_hazard_still_replays_instantly()
    {
        $drawing = SavedDrawing::factory()->published()->create([
            'user_id' => User::factory()->create()->id,
            'platform_color' => '#ff0000',
            'goal_color' => '#00ff00',
            'player_color' => '#0000ff',
            'hazard_color' => null,
        ]);

        $this->get("/play/{$drawing->id}")->assertRedirect('/game');

        $this->get('/game')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Game')
            ->where('hazardColor', null)
            ->etc()
        );
    }

    public function test_play_allows_the_owner_to_replay_an_unpublished_drawing()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::create([
            'user_id' => $user->id,
            'image_path' => 'levels/mine.jpg',
        ]);

        $this->actingAs($user)
            ->get("/play/{$drawing->id}")
            ->assertRedirect('/game-setting');
    }

    public function test_play_hides_unpublished_drawings_from_everyone_else()
    {
        $owner = User::factory()->create();
        $someoneElse = User::factory()->create();

        $drawing = SavedDrawing::create([
            'user_id' => $owner->id,
            'image_path' => 'levels/mine.jpg',
        ]);

        $this->get("/play/{$drawing->id}")->assertNotFound();
        $this->actingAs($someoneElse)->get("/play/{$drawing->id}")->assertNotFound();
    }

    public function test_play_with_an_unknown_drawing_is_a_404()
    {
        $this->get('/play/999')->assertNotFound();
    }

    public function test_a_published_drawings_image_is_visible_to_everyone()
    {
        Storage::fake('local');
        Storage::disk('local')->put('levels/published.jpg', 'image-bytes');

        $owner = User::factory()->create();
        $drawing = SavedDrawing::create([
            'user_id' => $owner->id,
            'image_path' => 'levels/published.jpg',
            'published' => true,
        ]);

        $this->get("/drawings/{$drawing->id}/image")->assertOk();
    }

    public function test_an_unpublished_drawings_image_is_only_visible_to_its_owner()
    {
        Storage::fake('local');
        Storage::disk('local')->put('levels/private.jpg', 'image-bytes');

        $owner = User::factory()->create();
        $someoneElse = User::factory()->create();

        $drawing = SavedDrawing::create([
            'user_id' => $owner->id,
            'image_path' => 'levels/private.jpg',
        ]);

        $this->get("/drawings/{$drawing->id}/image")->assertNotFound();
        $this->actingAs($someoneElse)->get("/drawings/{$drawing->id}/image")->assertNotFound();
        $this->actingAs($owner)->get("/drawings/{$drawing->id}/image")->assertOk();
    }

    public function test_deleting_a_drawing_removes_its_image_file()
    {
        Storage::fake('local');
        Storage::disk('local')->put('levels/mine.jpg', 'image-bytes');

        $user = User::factory()->create();
        $drawing = SavedDrawing::create([
            'user_id' => $user->id,
            'image_path' => 'levels/mine.jpg',
        ]);

        $this->actingAs($user)->delete("/drawing/{$drawing->id}");

        Storage::disk('local')->assertMissing('levels/mine.jpg');
    }

    public function test_saving_someone_elses_level_is_refused()
    {
        Storage::fake('local');
        Storage::disk('local')->put('levels/theirs.jpg', 'image-bytes');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $original = SavedDrawing::create([
            'user_id' => $owner->id,
            'image_path' => 'levels/theirs.jpg',
            'published' => true,
            'platform_color' => '#ff0000',
            'goal_color' => '#00ff00',
            'player_color' => '#0000ff',
            'hazard_color' => '#000000',
            'speed' => 5,
            'jump_height' => 10,
        ]);

        $this->actingAs($otherUser)->get("/play/{$original->id}");

        $this->actingAs($otherUser)
            ->post('/save-drawing', [
                'drawingId' => $original->id,
                'speed' => 5,
                'jumpHeight' => 10,
            ])
            ->assertForbidden();

        $this->assertSame(1, SavedDrawing::count());
        $this->assertCount(1, Storage::disk('local')->files('levels'));
    }

    public function test_uploading_the_same_picture_twice_stores_one_file()
    {
        Storage::fake('local');

        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)
                ->withSession($this->levelColours())
                ->post('/save-drawing', [
                    'levelImage' => UploadedFile::fake()->image('level.png', 8, 8),
                    'speed' => 5,
                    'jumpHeight' => 10,
                ]);
        }

        $this->assertSame(2, SavedDrawing::count());
        $this->assertCount(1, Storage::disk('local')->files('levels'));
        $this->assertSame(
            SavedDrawing::first()->image_path,
            SavedDrawing::orderByDesc('id')->first()->image_path
        );
    }

    public function test_uploading_different_pictures_stores_a_file_each()
    {
        Storage::fake('local');

        $user = User::factory()->create();

        foreach ([['one.png', 8], ['two.png', 16]] as [$name, $size]) {
            $this->actingAs($user)
                ->withSession($this->levelColours())
                ->post('/save-drawing', [
                    'levelImage' => UploadedFile::fake()->image($name, $size, $size),
                    'speed' => 5,
                    'jumpHeight' => 10,
                ]);
        }

        $this->assertCount(2, Storage::disk('local')->files('levels'));
    }

    public function test_saving_turns_the_page_into_a_replay_of_the_new_drawing()
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                'platformColor' => '#ff0000',
                'goalColor' => '#00ff00',
                'playerColor' => '#0000ff',
                'hazardColor' => '#000000',
            ])
            ->post('/save-drawing', [
                'levelImage' => UploadedFile::fake()->image('level.png'),
                'speed' => 5,
                'jumpHeight' => 10,
            ]);

        $drawing = SavedDrawing::firstOrFail();

        $this->actingAs($user)->get('/game')->assertInertia(
            fn (AssertableInertia $page) => $page->where('drawingId', $drawing->id)->etc()
        );
    }

    public function test_a_non_numeric_drawing_id_is_a_404()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/drawing/abc/publish')->assertNotFound();
        $this->actingAs($user)->delete('/drawing/abc')->assertNotFound();
    }

    public function test_an_out_of_range_page_redirects_to_the_last_real_page()
    {
        $user = User::factory()->create();

        SavedDrawing::factory()->count(12)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/account?page=2')
            ->assertRedirect(route('account', ['page' => 1]));
    }

    public function test_the_community_gallery_paginates()
    {
        $author = User::factory()->create();

        SavedDrawing::factory()->count(13)->create([
            'user_id' => $author->id,
            'published' => true,
        ]);

        $this->get('/community')->assertInertia(
            fn (AssertableInertia $page) => $page->has('drawings.data', 12)
        );

        $this->get('/community?page=2')->assertInertia(
            fn (AssertableInertia $page) => $page->has('drawings.data', 1)
        );
    }

    public function test_a_level_that_is_no_longer_available_sends_you_back_to_upload()
    {
        $drawing = SavedDrawing::factory()->published()->create([
            'user_id' => User::factory()->create()->id,
            'platform_color' => '#ff0000',
            'goal_color' => '#00ff00',
            'player_color' => '#0000ff',
            'hazard_color' => '#000000',
        ]);

        $this->get("/play/{$drawing->id}")->assertRedirect('/game');

        $drawing->update(['published' => false]);

        $this->get('/game')
            ->assertRedirect('/upload')
            ->assertSessionHas('message', 'That level is no longer available.');
    }

    public function test_saving_is_rate_limited()
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())->withSession([
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#000000',
        ]);

        foreach (range(1, 20) as $attempt) {
            $this->post('/save-drawing', [
                'levelImage' => UploadedFile::fake()->image("level{$attempt}.png"),
                'speed' => 5,
                'jumpHeight' => 10,
            ])->assertStatus(302);
        }

        $this->assertSame(20, SavedDrawing::count());

        $this->post('/save-drawing', [
            'levelImage' => UploadedFile::fake()->image('level21.png'),
            'speed' => 5,
            'jumpHeight' => 10,
        ])->assertStatus(429);
    }

    public function test_saving_cannot_name_a_drawing_you_may_not_play()
    {
        Storage::fake('local');

        $unpublished = SavedDrawing::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession([
                'platformColor' => '#ff0000',
                'goalColor' => '#00ff00',
                'playerColor' => '#0000ff',
                'hazardColor' => '#000000',
            ])
            ->post('/save-drawing', [
                'drawingId' => $unpublished->id,
                'speed' => 5,
                'jumpHeight' => 10,
            ])
            ->assertNotFound();

        $this->assertSame(1, SavedDrawing::count());
    }

    public function test_draw_page_renders_with_the_palette()
    {
        $this->get('/draw')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Draw')
            ->where('palette.platform', '#000000')
            ->where('palette.goal', '#00aa00')
            ->where('palette.player', '#0000ff')
            ->where('palette.hazard', '#ff0000')
        );
    }

    public function test_a_drawn_level_starts_the_game_without_colour_picking()
    {
        Storage::fake('local');

        $this->post('/start-game', [
            'platformColor' => '#000000',
            'goalColor' => '#00aa00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#ff0000',
        ])->assertRedirect('/game');

        $this->get('/game')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Game')
            ->where('platformColor', '#000000')
            ->where('hazardColor', '#ff0000')
            ->where('levelImage', null)
            ->etc()
        );

        $this->assertCount(0, Storage::disk('local')->files('levels'));
    }

    public function test_saving_does_not_throttle_registration()
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())->withSession([
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#000000',
        ]);

        foreach (range(1, 20) as $attempt) {
            $this->post('/save-drawing', [
                'levelImage' => UploadedFile::fake()->image("level{$attempt}.png"),
                'speed' => 5,
                'jumpHeight' => 10,
            ]);
        }

        $this->post('/logout');

        $this->post('/register', [
            'username' => 'drawer',
            'email' => 'drawer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
    }

    public function test_google_sign_in_creates_an_account()
    {
        $this->fakeGoogleUser([
            'id' => 'google-123',
            'name' => 'Jochen Tombal',
            'nickname' => 'jochen',
            'email' => 'jochen@example.com',
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'email' => 'jochen@example.com',
            'username' => 'jochen',
            'google_id' => 'google-123',
        ]);
        $this->assertAuthenticated();
    }

    public function test_google_sign_in_avoids_a_duplicate_username()
    {
        User::factory()->create(['username' => 'jochen', 'email' => 'first@example.com']);

        $this->fakeGoogleUser([
            'id' => 'google-456',
            'name' => 'Another Jochen',
            'nickname' => 'jochen',
            'email' => 'second@example.com',
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'email' => 'second@example.com',
            'username' => 'jochen2',
        ]);
    }

    public function test_google_sign_in_cleans_up_an_unusable_nickname()
    {
        $this->fakeGoogleUser([
            'id' => 'google-789',
            'name' => 'Jurgen Muller',
            'nickname' => 'Jürgen Müller!',
            'email' => 'jurgen@example.com',
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'email' => 'jurgen@example.com',
            'username' => 'JurgenMuller',
        ]);
    }

    public function test_google_sign_in_truncates_a_very_long_nickname()
    {
        $this->fakeGoogleUser([
            'id' => 'google-long',
            'name' => 'Long Name',
            'nickname' => str_repeat('a', 120),
            'email' => 'long@example.com',
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertSame(
            User::USERNAME_MAX,
            strlen(User::where('email', 'long@example.com')->value('username'))
        );
    }

    public function test_google_sign_in_falls_back_when_nothing_survives()
    {
        $this->fakeGoogleUser([
            'id' => 'google-empty',
            'name' => 'Symbols',
            'nickname' => '!!! ***',
            'email' => 'symbols@example.com',
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/');

        $username = User::where('email', 'symbols@example.com')->value('username');

        $this->assertMatchesRegularExpression(User::USERNAME_PATTERN, $username);
        $this->assertGreaterThanOrEqual(User::USERNAME_MIN, strlen($username));
    }

    public function test_google_sign_in_keeps_a_deduplicated_name_within_the_limit()
    {
        $taken = str_repeat('b', User::USERNAME_MAX);
        User::factory()->create(['username' => $taken, 'email' => 'first@example.com']);

        $this->fakeGoogleUser([
            'id' => 'google-dupe',
            'name' => 'Same Again',
            'nickname' => $taken,
            'email' => 'second@example.com',
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/');

        $username = User::where('email', 'second@example.com')->value('username');

        $this->assertLessThanOrEqual(User::USERNAME_MAX, strlen($username));
        $this->assertNotSame($taken, $username);
    }

    public function test_google_sign_in_does_not_overwrite_an_existing_password_account()
    {
        $user = User::factory()->create([
            'username' => 'chosen_name',
            'email' => 'both@example.com',
            'password' => bcrypt('RealPassword123!'),
        ]);

        $this->fakeGoogleUser([
            'id' => 'google-789',
            'name' => 'Google Display Name',
            'nickname' => 'googlenick',
            'email' => 'both@example.com',
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/');

        $user->refresh();

        $this->assertSame('google-789', $user->google_id);
        $this->assertSame('chosen_name', $user->username);
        $this->assertTrue(Hash::check('RealPassword123!', $user->password));

        $this->post('/logout');
        $this->post('/login', [
            'email' => 'both@example.com',
            'password' => 'RealPassword123!',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_pruning_removes_unreferenced_level_images()
    {
        Storage::fake('local');

        $user = User::factory()->create();

        Storage::disk('local')->put('levels/saved.jpg', 'image-bytes');
        Storage::disk('local')->put('levels/orphan.jpg', 'image-bytes');

        SavedDrawing::create([
            'user_id' => $user->id,
            'image_path' => 'levels/saved.jpg',
        ]);

        $this->artisan('levels:prune', ['--hours' => 0])->assertSuccessful();

        Storage::disk('local')->assertExists('levels/saved.jpg');
        Storage::disk('local')->assertMissing('levels/orphan.jpg');
    }

    public function test_pruning_leaves_recent_uploads_alone()
    {
        Storage::fake('local');
        Storage::disk('local')->put('levels/fresh.jpg', 'image-bytes');

        $this->artisan('levels:prune')->assertSuccessful();

        Storage::disk('local')->assertExists('levels/fresh.jpg');
    }

    /**
     * @return array<string, string>
     */
    private function levelColours(): array
    {
        return [
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#000000',
        ];
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function fakeGoogleUser(array $attributes): void
    {
        Socialite::fake('google', (new SocialiteUser)->map($attributes));
    }
}
