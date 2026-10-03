<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/search')->assertRedirect('/login');
    }

    public function test_search_page_renders_without_query(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get('/search')
            ->assertOk()
            ->assertSee('Търсене на книги');

        Http::assertNothingSent();
    }

    public function test_results_are_displayed(): void
    {
        Http::fake(['*/volumes?*' => Http::response(['items' => [[
            'id' => 'abc123',
            'volumeInfo' => ['title' => 'Дюн', 'authors' => ['Франк Хърбърт']],
        ]]])]);

        $this->actingAs(User::factory()->create())
            ->get('/search?q=dune')
            ->assertOk()
            ->assertSee('Дюн')
            ->assertSee('Франк Хърбърт');
    }

    public function test_empty_results_show_a_message(): void
    {
        Http::fake(['*/volumes?*' => Http::response(['totalItems' => 0])]);

        $this->actingAs(User::factory()->create())
            ->get('/search?q=zzzzqqq')
            ->assertOk()
            ->assertSee('Няма намерени книги');
    }

    public function test_api_failure_shows_an_error_instead_of_crashing(): void
    {
        Http::fake(['*/volumes?*' => Http::response(['error' => 'quota'], 429)]);

        $this->actingAs(User::factory()->create())
            ->get('/search?q=dune')
            ->assertOk()
            ->assertSee('не е достъпно в момента');
    }

    public function test_overlong_query_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/search?q='.str_repeat('a', 201))
            ->assertSessionHasErrors('q');
    }
}
