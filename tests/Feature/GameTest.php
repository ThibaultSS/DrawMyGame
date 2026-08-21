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

    // 1. Home page loads correctly, as an Inertia page
    public function test_home_page_loads()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Home'));
    }

    // 2. Upload page loads correctly
    public function test_upload_page_loads()
    {
        $response = $this->get('/upload');
        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Upload'));
    }

    // 2b. About page loads correctly
    public function test_about_page_loads()
    {
        $response = $this->get('/about');
        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('About'));
    }

    // 2c. Cookies page loads correctly
    public function test_cookies_page_loads()
    {
        $response = $this->get('/cookies');
        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Cookies'));
    }

    // 3. Community page loads correctly and lists only published drawings, with
    // just the fields the cards draw
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
                ->hasAll(['id', 'image', 'title', 'description', 'likes', 'dislikes'])
            )
        );
    }

    // 4. Account page redirects to login when not logged in
    public function test_account_page_redirects_when_not_logged_in()
    {
        $response = $this->get('/account');
        $response->assertRedirect('/login');
    }

    // 5. Account page loads when logged in, as an Inertia page carrying only the
    // fields the interface draws
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

    // 5b. Only your own drawings are listed
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

    // 5c. Publishing and unpublishing report back instead of silently reloading
    // the page. The details live with the publish; see CommunityTest for those.
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

    // 5d. Deleting reports back too
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

    // 5e. Someone else's drawing is a 404, not a 403: a 403 would confirm that the
    // id exists.
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

    // 6. User can register with valid data
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

        // Registering signs you in. Before, it created the account and left you a
        // guest, having to log in again with credentials typed a second earlier.
        $this->assertAuthenticated();
    }

    // 7. User cannot register with duplicate email
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

    // 8. User can login with correct credentials
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

    // 9. User cannot login with wrong password
    // The error now sits on 'email' rather than 'password': the message is
    // deliberately generic, so it is no longer tied to which field was wrong.
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

    // 9b. The login page is served as an Inertia page, not a Blade view
    public function test_login_page_renders_the_inertia_component()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Login'));
    }

    // 9c. A wrong password and an unknown address are indistinguishable, so the
    // login form cannot be used to find out which emails have an account.
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

    // 9d. Repeated failures lock the form, so a generic error message cannot just
    // be brute forced through.
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

        // The sixth is refused before the password is even checked. Sending the
        // correct one and still being a guest is the proof: the lock is real, not
        // just a different message.
        $this->post('/login', [
            'email' => 'throttled@example.com',
            'password' => 'CorrectPassword123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // 9e. After being bounced to the login page, logging in returns you to where
    // you were going rather than to the home page.
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

    // 9f. Someone already signed in has no business on the login form.
    public function test_login_page_redirects_a_user_who_is_already_signed_in()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect('/');
    }

    // 9g. Logging out throws the session away rather than only forgetting the user.
    public function test_logout_invalidates_the_session()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
        $this->get('/account')->assertRedirect('/login');
    }

    // 9h. A one-character password used to be accepted.
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

    // 10. Logged in user can save a drawing. This is where the level image
    // finally reaches the server — the browser held it until now — and the whole
    // game goes with it: the session's colours plus the slider positions.
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

    // 10b. Slider values outside the sliders' own range never came from the
    // interface and are refused
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

    // 10c. The defining behaviour of the whole flow: playing a level never puts
    // it on the server. Only saving does.
    public function test_a_level_is_not_stored_until_it_is_saved()
    {
        Storage::fake('local');

        $this->post('/start-game', [
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#000000',
        ])->assertRedirect('/game');

        // The game page renders and hands the engine its colours without the
        // server ever having seen the picture.
        $this->get('/game')->assertOk();

        $this->assertCount(0, Storage::disk('local')->files('levels'));
    }

    // 11. Saving without a file, and without naming a drawing the server
    // already has, is rejected instead of crashing on ->store()
    public function test_saving_nothing_is_rejected()
    {
        $this->actingAs(User::factory()->create())
            ->post('/save-drawing', ['speed' => 5, 'jumpHeight' => 10])
            ->assertSessionHasErrors('levelImage');
    }

    // 11b. The mime check looks at the content, so a PDF with any name is not
    // an image
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

    // 11c. Images above the size cap are refused
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

    // 12. A level the browser is holding gives the colour picker nothing to
    // serve: the page finds the picture in its own level store
    public function test_game_setting_page_has_no_image_for_a_browser_held_level()
    {
        $this->get('/game-setting')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('GameSetting')
            ->where('image', null)
        );
    }

    // 12b. Replaying a drawing saved before the settings columns existed is the
    // one case that still arrives here with an image the server has to serve
    public function test_game_setting_page_shows_a_replayed_drawings_image()
    {
        $legacy = SavedDrawing::factory()->published()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->get("/play/{$legacy->id}")->assertRedirect('/game-setting');

        // The image URL points at the serving route, not at public storage:
        // the file itself lives on the private disk.
        $this->get('/game-setting')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('GameSetting')
            ->where('image', route('drawings.image', $legacy))
        );
    }

    // 13. Starting the game stores the colours and moves to the game page
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

    // 13b. Three colours are required — something to stand on, somewhere to get
    // to and someone to move. The old form let an empty submit through.
    public function test_start_game_requires_the_three_essential_colours()
    {
        $this->post('/start-game')->assertSessionHasErrors([
            'platformColor',
            'goalColor',
            'playerColor',
        ]);
    }

    // 13c. A hazard is not one of them: a level with nothing dangerous in it is
    // still a level, and demanding one meant inventing a danger to get past the
    // colour picker
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

    // 13d. And the hazard from an earlier level must not linger into one that
    // has none — validated() leaves out a key that was never sent
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

    // 14. The game page hands the engine its colours, and no image at all: the
    // browser has the picture, so there is nothing for the server to point at
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

    // 14b. A saved drawing is the one level the server does serve, and it comes
    // with its id so that pressing Save updates it instead of duplicating it
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

    // 14c. Without the colours there is nothing to build, so the game page
    // starts the flow over instead of booting a broken game
    public function test_game_page_requires_the_full_session()
    {
        $this->get('/game')->assertRedirect('/upload');

        // A partial set is no better than none.
        $this->withSession(['platformColor' => '#ff0000'])
            ->get('/game')
            ->assertRedirect('/upload');
    }

    // 15. Replaying a published drawing re-enters the flow at colour picking
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

    // 15a. A drawing saved with its game settings skips colour picking and
    // starts playing immediately, exactly as its author tuned it
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

    // 15a-2. The game page then hands those to the engine as props
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

    // 15a-3. Picking colours afresh resets the feel: a speed left behind by an
    // earlier replay must not leak into a new game
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

    // 15a-4. Replaying a drawing WITHOUT settings clears the previous game's
    // session: stale colours would let /game boot this image against another
    // picture's palette, and a Save there would bake the mismatch in
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

    // 15a-5. Starting a fresh game forgets which drawing was being replayed.
    // Without this, saving a level the browser is holding would quietly update
    // whichever drawing was played last — someone else's, even.
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

    // 15a-6. Re-saving your own drawing updates it in place — new feel, same
    // card, no duplicate file. This is also how a pre-settings drawing gains
    // its settings.
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

        // Replay it, then save with a different feel. The page knows which
        // drawing it is showing, so it says so rather than sending the image
        // back up again.
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

    // 15a-7. A level saved without a hazard is complete, so replaying it starts
    // immediately instead of being sent back through colour picking
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

    // 15b. An unpublished drawing can still be replayed by its owner
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

    // 15c. For everyone else an unpublished drawing does not exist — 404, not
    // 403, so the id cannot be probed
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

    // 16. Level images live on the private disk and are served through a route
    // that checks who may see what
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

    // 16b. An unpublished drawing's image is only visible to its owner
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

    // 17. Deleting a drawing also removes its file from disk
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

    // 17b. Saving someone else's level takes a copy of the file, so the two
    // drawings do not share one image: without this the original owner's
    // delete became a no-op, and their image stayed on display under another
    // name.
    public function test_saving_someone_elses_level_copies_the_file()
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

        // Play the published level, then save it. The server already has that
        // image, so the browser names the drawing rather than uploading a file
        // it never held.
        $this->actingAs($otherUser)->get("/play/{$original->id}");
        $this->actingAs($otherUser)->post('/save-drawing', [
            'drawingId' => $original->id,
            'speed' => 5,
            'jumpHeight' => 10,
        ]);

        $copy = SavedDrawing::where('user_id', $otherUser->id)->firstOrFail();

        $this->assertNotSame($original->image_path, $copy->image_path);
        Storage::disk('local')->assertExists($copy->image_path);

        // The owner deleting their drawing really removes their file, and
        // leaves the copy untouched.
        $this->actingAs($owner)->delete("/drawing/{$original->id}");

        Storage::disk('local')->assertMissing('levels/theirs.jpg');
        Storage::disk('local')->assertExists($copy->image_path);
    }

    // 17c. After a save the game page comes back knowing which drawing it is
    // now showing, so pressing Save again updates that drawing rather than
    // storing a second copy of the same level
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

    // 18. A drawing id that is not a number is a 404, not a 500 that would show
    // a stack trace on a deploy left in debug mode
    public function test_a_non_numeric_drawing_id_is_a_404()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/drawing/abc/publish')->assertNotFound();
        $this->actingAs($user)->delete('/drawing/abc')->assertNotFound();
    }

    // 19. Deleting the last drawing on a page sends you to a page that still
    // exists, rather than showing "you have no drawings" while you have plenty
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

    // 20. A drawing that stops being playable while someone is on it restarts
    // the flow, instead of booting the engine against an image that 404s
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

        // The owner unpublishes it while someone else is playing.
        $drawing->update(['published' => false]);

        $this->get('/game')
            ->assertRedirect('/upload')
            ->assertSessionHas('message', 'That level is no longer available.');
    }

    // 21. Saving is throttled: it is the one route that accepts a file, and
    // each accepted one costs disk
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

        // The twenty really were saved, so the limit is what stops the next one
        // rather than a validation error that would 302 just the same.
        $this->assertSame(20, SavedDrawing::count());

        $this->post('/save-drawing', [
            'levelImage' => UploadedFile::fake()->image('level21.png'),
            'speed' => 5,
            'jumpHeight' => 10,
        ])->assertStatus(429);
    }

    // 21b. A drawingId is just a number from the page, so it buys nothing that
    // opening the drawing would not: one you may not play is a 404
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

    // 24. The Draw page carries the exact palette it paints with, which is what
    // it will hand to /start-game
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

    // 24b. A drawn level skips colour picking — the palette is fixed, so those
    // values go straight to the endpoint the eyedropper posts to — and the
    // canvas stays in the browser like any other level
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

    // 24b-2. Saving and registering do not share a throttle bucket: filling the
    // save limit must not lock the registration form
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

    // 23. Signing in with Google creates an account the first time
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

    // 23b. Two people with the same Google nickname each get a free username
    // instead of a constraint violation
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

    // 23c. Signing in with Google on an account that already has a password
    // links the two. It used to overwrite the password with a random hash and
    // rename the user, locking them out of their own account for good — there
    // is no password reset to recover through.
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

        // And the password still works.
        $this->post('/logout');
        $this->post('/login', [
            'email' => 'both@example.com',
            'password' => 'RealPassword123!',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    // 22. An upload nobody saved is swept up; one a drawing points at stays
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

        // --hours=0 makes every file old enough to consider, so the only thing
        // protecting one is a drawing that points at it.
        $this->artisan('levels:prune', ['--hours' => 0])->assertSuccessful();

        Storage::disk('local')->assertExists('levels/saved.jpg');
        Storage::disk('local')->assertMissing('levels/orphan.jpg');
    }

    // 22b. The grace period protects a file that was just uploaded: it has no
    // drawing yet either, because its owner is still picking colours
    public function test_pruning_leaves_recent_uploads_alone()
    {
        Storage::fake('local');
        Storage::disk('local')->put('levels/fresh.jpg', 'image-bytes');

        $this->artisan('levels:prune')->assertSuccessful();

        Storage::disk('local')->assertExists('levels/fresh.jpg');
    }

    /**
     * Socialite's fake takes a provider user, and this version has no
     * User::fake() to build one with.
     *
     * @param  array<string, string>  $attributes
     */
    private function fakeGoogleUser(array $attributes): void
    {
        Socialite::fake('google', (new SocialiteUser)->map($attributes));
    }
}
