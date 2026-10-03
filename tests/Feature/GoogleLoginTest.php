<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleUser(array $overrides = []): void
    {
        $googleUser = (new SocialiteUser)->map([
            'id' => 'g-123',
            'name' => 'Ana Ivanova',
            'email' => 'ana@example.com',
            'avatar' => 'https://example.com/ana.png',
        ]);
        $googleUser->user = $overrides + ['email_verified' => true];

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirects_to_google(): void
    {
        config(['services.google' => [
            'client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://127.0.0.1:8000/auth/google/callback',
        ]]);

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirectContains('accounts.google.com');
    }

    public function test_new_user_is_created_and_logged_in(): void
    {
        $this->mockGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $user = User::firstWhere('email', 'ana@example.com');
        $this->assertNotNull($user);
        $this->assertSame('g-123', $user->google_id);
        $this->assertSame('Ana Ivanova', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_user_with_same_email_is_linked(): void
    {
        $existing = User::factory()->create(['email' => 'ana@example.com']);
        $this->mockGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertSame(1, User::count());
        $this->assertSame('g-123', $existing->fresh()->google_id);
        $this->assertAuthenticatedAs($existing);
    }

    public function test_returning_google_user_is_not_duplicated(): void
    {
        User::factory()->create(['email' => 'old@example.com', 'google_id' => 'g-123']);
        $this->mockGoogleUser();

        $this->get('/auth/google/callback');

        $this->assertSame(1, User::count());
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $this->mockGoogleUser(['email_verified' => false]);

        $this->get('/auth/google/callback')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_callback_failure_returns_to_login_with_error(): void
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_page_shows_google_button_only_when_configured(): void
    {
        config(['services.google.client_id' => null]);
        $this->get('/login')->assertDontSee('Вход с Google');

        config(['services.google.client_id' => 'id']);
        $this->get('/login')->assertSee('Вход с Google');
    }
}
