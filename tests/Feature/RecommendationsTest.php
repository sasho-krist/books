<?php

namespace Tests\Feature;

use App\Jobs\GenerateRecommendations;
use App\Models\Book;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecommendationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.gemini.key' => 'test-key']);
    }

    private function userWithReadBook(string $title = 'Под игото'): User
    {
        $user = User::factory()->create();
        $book = Book::create(['google_id' => 'g-'.md5($title), 'title' => $title, 'authors' => ['Иван Вазов']]);
        $user->books()->attach($book->id, ['status' => 'read', 'rating' => 5]);

        return $user;
    }

    /** A Gemini generateContent response whose text is the given JSON payload. */
    private function geminiResponse(array $items): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($items, JSON_UNESCAPED_UNICODE)]]]]]];
    }

    private function items(int $n = 5): array
    {
        return array_map(fn (int $i) => [
            'title' => "Книга {$i}", 'author' => "Автор {$i}", 'reason' => "Защото ти харесва {$i}.",
        ], range(1, $n));
    }

    public function test_guests_cannot_request_recommendations(): void
    {
        $this->post('/recommendations')->assertRedirect('/login');
    }

    public function test_requesting_dispatches_a_queued_job_and_marks_it_pending(): void
    {
        Queue::fake();
        $user = $this->userWithReadBook();

        $this->actingAs($user)->post('/recommendations')->assertSessionHas('status');

        Queue::assertPushed(GenerateRecommendations::class, fn ($job) => $job->user->is($user));
        $this->assertTrue(Cache::has(GenerateRecommendations::pendingKey($user->id)));
        $this->actingAs($user)->get('/dashboard')->assertSee('Генерирам препоръки…');
    }

    public function test_double_click_queues_only_one_job(): void
    {
        Queue::fake();
        $user = $this->userWithReadBook();

        $this->actingAs($user)->post('/recommendations');
        $this->actingAs($user)->post('/recommendations');

        Queue::assertPushed(GenerateRecommendations::class, 1);
    }

    public function test_user_without_read_books_gets_a_message_and_no_job(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())->post('/recommendations')->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_job_stores_recommendations_and_shows_them_on_the_dashboard(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse($this->items()))]);
        $user = $this->userWithReadBook();

        $this->actingAs($user)->post('/recommendations'); // sync queue in tests

        $this->assertSame(5, $user->recommendations()->count());
        $this->assertFalse(Cache::has(GenerateRecommendations::pendingKey($user->id)));

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Книга 1')
            ->assertSee('Автор 1')
            ->assertSee('Защото ти харесва 1.')
            ->assertSee('/search?q=', false);
    }

    public function test_request_sends_read_books_ratings_and_the_api_key_to_gemini(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse($this->items()))]);
        $user = $this->userWithReadBook('Под игото');

        $this->actingAs($user)->post('/recommendations');

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
                && $request->header('x-goog-api-key')[0] === 'test-key'
                && $request['generationConfig']['responseMimeType'] === 'application/json'
                && str_contains($prompt, 'Под игото')
                && str_contains($prompt, 'оценка 5/5');
        });
    }

    public function test_new_batch_replaces_the_old_one(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse($this->items(3)))]);
        $user = $this->userWithReadBook();
        Recommendation::create(['user_id' => $user->id, 'title' => 'Стара препоръка']);

        $this->actingAs($user)->post('/recommendations');

        $this->assertSame(3, $user->recommendations()->count());
        $this->assertSame(0, $user->recommendations()->where('title', 'Стара препоръка')->count());
    }

    public function test_books_already_on_the_shelf_and_duplicates_are_dropped_and_count_is_capped(): void
    {
        $items = [
            ['title' => 'под игото', 'author' => 'Вазов', 'reason' => 'вече я имаш'],
            ['title' => 'Нова 1', 'author' => 'А', 'reason' => 'р'],
            ['title' => 'Нова 1', 'author' => 'А', 'reason' => 'дубликат'],
            ...array_map(fn ($i) => ['title' => "Друга {$i}", 'author' => 'Б', 'reason' => 'р'], range(1, 8)),
        ];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse($items))]);
        $user = $this->userWithReadBook('Под игото');

        $this->actingAs($user)->post('/recommendations');

        $titles = $user->recommendations()->pluck('title')->all();
        $this->assertCount(5, $titles);
        $this->assertNotContains('под игото', $titles);
        $this->assertSame(1, count(array_keys($titles, 'Нова 1')));
    }

    public function test_html_in_model_output_is_escaped(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
            ['title' => '<script>alert(1)</script>', 'author' => 'X', 'reason' => 'y'],
        ]))]);
        $user = $this->userWithReadBook();

        $this->actingAs($user)->post('/recommendations');

        $this->actingAs($user)->get('/dashboard')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_api_failure_shows_an_error_and_keeps_old_recommendations(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429)]);
        $user = $this->userWithReadBook();
        Recommendation::create(['user_id' => $user->id, 'title' => 'Стара препоръка']);

        $this->actingAs($user)->post('/recommendations');

        $this->assertSame(['Стара препоръка'], $user->recommendations()->pluck('title')->all());
        $this->assertFalse(Cache::has(GenerateRecommendations::pendingKey($user->id)));
        $this->actingAs($user)->get('/dashboard')
            ->assertSee('не можаха да бъдат генерирани')
            ->assertSee('Стара препоръка');
    }

    public function test_invalid_model_output_is_handled(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'not json']]]]],
        ])]);
        $user = $this->userWithReadBook();

        $this->actingAs($user)->post('/recommendations');

        $this->assertSame(0, $user->recommendations()->count());
        $this->actingAs($user)->get('/dashboard')->assertSee('не можаха да бъдат генерирани');
    }

    public function test_missing_api_key_is_reported_to_the_user(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();
        $user = $this->userWithReadBook();

        $this->actingAs($user)->post('/recommendations');

        Http::assertNothingSent();
        $this->actingAs($user)->get('/dashboard')->assertSee('не можаха да бъдат генерирани');
    }

    public function test_recommendations_are_private_to_each_user(): void
    {
        $other = User::factory()->create();
        Recommendation::create(['user_id' => $other->id, 'title' => 'Чужда препоръка']);

        $this->actingAs($this->userWithReadBook())->get('/dashboard')->assertDontSee('Чужда препоръка');
    }

    public function test_dashboard_prompts_to_mark_a_book_as_read_when_none_is(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('Маркирай поне една книга като „Прочетени“');
    }
}
