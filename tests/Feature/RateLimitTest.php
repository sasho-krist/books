<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake([
            'chitanka.info/*' => Http::response(['result' => []]),
            '*/volumes?*' => Http::response(['totalItems' => 0]),
        ]);
    }

    public function test_search_is_limited_to_30_requests_per_minute(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($user)->get('/search?q=book'.$i)->assertOk();
        }

        $this->actingAs($user)->get('/search?q=one-more')
            ->assertStatus(429)
            ->assertSee('Твърде много заявки');
    }

    public function test_limit_is_per_user(): void
    {
        $heavy = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 1; $i <= 31; $i++) {
            $this->actingAs($heavy)->get('/search?q=book'.$i);
        }

        $this->actingAs($heavy)->get('/search?q=again')->assertStatus(429);
        $this->actingAs($other)->get('/search?q=fresh')->assertOk();
    }

    public function test_limit_applies_before_any_external_request(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($user)->get('/search?q=book'.$i);
        }
        Http::fake(); // reset recorded requests

        $this->actingAs($user)->get('/search?q=blocked')->assertStatus(429);

        Http::assertNothingSent();
    }

    public function test_adding_books_is_rate_limited_too(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($user)->post('/my-books', ['google_id' => 'x', 'status' => 'nope']);
        }

        $this->actingAs($user)->post('/my-books', ['google_id' => 'x', 'status' => 'nope'])->assertStatus(429);
    }
}
