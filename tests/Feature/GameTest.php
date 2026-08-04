<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    // 1. Home page loads correctly
    public function test_home_page_loads()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    // 2. Upload page loads correctly
    public function test_upload_page_loads()
    {
        $response = $this->get('/upload');
        $response->assertStatus(200);
    }

    // 3. Community page loads correctly
    public function test_community_page_loads()
    {
        $response = $this->get('/community');
        $response->assertStatus(200);
    }

    // 4. Account page redirects to login when not logged in
    public function test_account_page_redirects_when_not_logged_in()
    {
        $response = $this->get('/account');
        $response->assertRedirect('/login');
    }

    // 5. Account page loads when logged in
    public function test_account_page_loads_when_logged_in()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/account');
        $response->assertStatus(200);
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

    // 10. Logged in user can save a drawing
    public function test_user_can_save_drawing()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['uploadedLevel' => 'levels/test.jpg'])
            ->post('/save-drawing');

        $this->assertDatabaseHas('saved_drawings', [
            'user_id' => $user->id,
            'image_path' => 'levels/test.jpg',
        ]);
    }
}
