<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_from_home_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_signed_in_users_are_sent_from_home_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/dashboard');
    }

    public function test_ui_is_in_bulgarian(): void
    {
        $this->get('/login')
            ->assertSee('lang="bg"', false)
            ->assertSee('Забравена парола?')
            ->assertSee('Запомни ме')
            ->assertDontSee('Remember me');
    }

    public function test_validation_messages_are_in_bulgarian(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/search?q='.str_repeat('a', 201))
            ->assertSessionHasErrors(['q' => 'Полето търсене не може да е по-дълго от 200 символа.']);
    }
}
