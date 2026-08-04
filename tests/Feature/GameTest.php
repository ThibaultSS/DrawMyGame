<?php

namespace Tests\Feature;

use App\Models\SavedDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
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
                ->hasAll(['id', 'image'])
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

    // 5c. Publishing reports back instead of silently reloading the page
    public function test_user_can_toggle_publish_on_their_own_drawing()
    {
        $user = User::factory()->create();
        $drawing = SavedDrawing::create([
            'user_id' => $user->id,
            'image_path' => 'levels/mine.jpg',
        ]);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish")
            ->assertSessionHas('message', 'Drawing published.');

        $this->assertTrue($drawing->fresh()->published);

        $this->actingAs($user)
            ->post("/drawing/{$drawing->id}/publish")
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

        $this->actingAs($user)->post("/drawing/{$drawing->id}/publish")->assertNotFound();
        $this->actingAs($user)->delete("/drawing/{$drawing->id}")->assertNotFound();

        $this->assertFalse($drawing->fresh()->published);
        $this->assertNotSoftDeleted($drawing);
    }

    // 6. User can register with valid data
    public function test_user_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
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
            'name' => 'Another User',
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

    // 10. Logged in user can save a drawing, and hears back that it worked
    public function test_user_can_save_drawing()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['uploadedLevel' => 'levels/test.jpg'])
            ->post('/save-drawing')
            ->assertSessionHas('message', 'Drawing saved.');

        $this->assertDatabaseHas('saved_drawings', [
            'user_id' => $user->id,
            'image_path' => 'levels/test.jpg',
        ]);
    }

    // 11. Uploading a level stores the file on the private disk, remembers it
    // in the session, and moves on to colour picking
    public function test_uploading_a_level_moves_on_to_colour_picking()
    {
        Storage::fake('local');

        $response = $this->post('/upload-level', [
            'levelImage' => UploadedFile::fake()->image('level.png'),
        ]);

        $response->assertRedirect('/game-setting');
        $response->assertSessionHas('uploadedLevel');

        Storage::disk('local')->assertExists(session('uploadedLevel'));
    }

    // 11b. An upload without a file is rejected instead of crashing on ->store()
    public function test_uploading_nothing_is_rejected()
    {
        $this->post('/upload-level')->assertSessionHasErrors('levelImage');
    }

    // 11c. The mime check looks at the content, so a PDF with any name is not
    // an image
    public function test_uploading_a_non_image_is_rejected()
    {
        Storage::fake('local');

        $this->post('/upload-level', [
            'levelImage' => UploadedFile::fake()->create('level.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('levelImage');
    }

    // 11d. Uploads above the size cap are refused
    public function test_uploading_an_oversized_image_is_rejected()
    {
        Storage::fake('local');

        $this->post('/upload-level', [
            'levelImage' => UploadedFile::fake()->image('level.png')->size(10 * 1024 + 1),
        ])->assertSessionHasErrors('levelImage');
    }

    // 12. The colour-picking page needs an uploaded level to show; without one
    // the flow starts over
    public function test_game_setting_page_requires_an_uploaded_level()
    {
        $this->get('/game-setting')->assertRedirect('/upload');
    }

    public function test_game_setting_page_shows_the_uploaded_level()
    {
        $response = $this->withSession(['uploadedLevel' => 'levels/test.jpg'])
            ->get('/game-setting');

        // The image URL points at the serving route, not at public storage:
        // the file itself lives on the private disk.
        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('GameSetting')
            ->where('image', route('uploaded-level'))
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

    // 13b. All four colours are required: the engine cannot build a level from a
    // partial set, which the old form allowed
    public function test_start_game_requires_all_four_colours()
    {
        $this->post('/start-game')->assertSessionHasErrors([
            'platformColor',
            'goalColor',
            'playerColor',
            'hazardColor',
        ]);
    }

    // 14. The game page hands the engine everything it needs as props
    public function test_game_page_renders_with_the_level_and_colours()
    {
        $response = $this->withSession([
            'uploadedLevel' => 'levels/test.jpg',
            'platformColor' => '#ff0000',
            'goalColor' => '#00ff00',
            'playerColor' => '#0000ff',
            'hazardColor' => '#000000',
        ])->get('/game');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Game')
            ->where('levelImage', route('uploaded-level'))
            ->where('platformColor', '#ff0000')
            ->where('goalColor', '#00ff00')
            ->where('playerColor', '#0000ff')
            ->where('hazardColor', '#000000')
        );
    }

    // 14b. Without a complete session there is nothing to build, so the game
    // page starts the flow over instead of booting a broken game
    public function test_game_page_requires_the_full_session()
    {
        $this->get('/game')->assertRedirect('/upload');

        $this->withSession(['uploadedLevel' => 'levels/test.jpg'])
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
        $response->assertSessionHas('uploadedLevel', 'levels/mine.jpg');
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

    // 16c. The in-session upload is served to the session that owns it, and to
    // nobody without one
    public function test_the_uploaded_level_image_belongs_to_the_session()
    {
        Storage::fake('local');
        Storage::disk('local')->put('levels/session.jpg', 'image-bytes');

        $this->get('/uploaded-level')->assertNotFound();

        $this->withSession(['uploadedLevel' => 'levels/session.jpg'])
            ->get('/uploaded-level')
            ->assertOk();
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

    // 17b. Replaying copies image paths between saves, so the file stays as
    // long as any other drawing still uses it
    public function test_deleting_a_drawing_keeps_a_file_that_another_drawing_uses()
    {
        Storage::fake('local');
        Storage::disk('local')->put('levels/shared.jpg', 'image-bytes');

        $user = User::factory()->create();
        $someoneElse = User::factory()->create();

        $mine = SavedDrawing::create([
            'user_id' => $user->id,
            'image_path' => 'levels/shared.jpg',
        ]);
        SavedDrawing::create([
            'user_id' => $someoneElse->id,
            'image_path' => 'levels/shared.jpg',
        ]);

        $this->actingAs($user)->delete("/drawing/{$mine->id}");

        Storage::disk('local')->assertExists('levels/shared.jpg');
    }
}
