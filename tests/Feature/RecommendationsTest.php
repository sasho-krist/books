<?php

namespace Tests\Feature;

use App\Jobs\GenerateRecommendations;
use App\Models\Book;
use App\Models\Recommendation;
use App\Models\User;
use App\Services\Claude;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RecommendationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function userWithReadBook(string $title = 'Под игото'): User
    {
        $user = User::factory()->create();
        $book = Book::create(['google_id' => 'g-'.md5($title), 'title' => $title, 'authors' => ['Иван Вазов']]);
        $user->books()->attach($book->id, ['status' => 'read', 'rating' => 5]);

        return $user;
    }

    /** Make Claude answer with the given recommendations. */
    private function claudeAnswers(array $items): void
    {
        $this->mock(Claude::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('generateJson')->andReturn(['recommendations' => $items]));
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
        $this->claudeAnswers($this->items());
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

    public function test_prompt_contains_read_books_and_ratings_and_the_schema_is_strict(): void
    {
        $captured = [];
        $this->mock(Claude::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('generateJson')
                ->andReturnUsing(function (string $prompt, array $schema) use (&$captured) {
                    $captured = compact('prompt', 'schema');

                    return ['recommendations' => $this->items(1)];
                });
        });
        $user = $this->userWithReadBook('Под игото');

        $this->actingAs($user)->post('/recommendations');

        $this->assertStringContainsString('Под игото', $captured['prompt']);
        $this->assertStringContainsString('оценка 5/5', $captured['prompt']);
        $this->assertSame('object', $captured['schema']['type']);
        $this->assertFalse($captured['schema']['additionalProperties']);
        $this->assertSame(['title', 'author', 'reason'], $captured['schema']['properties']['recommendations']['items']['required']);
    }

    public function test_new_batch_replaces_the_old_one(): void
    {
        $this->claudeAnswers($this->items(3));
        $user = $this->userWithReadBook();
        Recommendation::create(['user_id' => $user->id, 'title' => 'Стара препоръка']);

        $this->actingAs($user)->post('/recommendations');

        $this->assertSame(3, $user->recommendations()->count());
        $this->assertSame(0, $user->recommendations()->where('title', 'Стара препоръка')->count());
    }

    public function test_books_already_on_the_shelf_and_duplicates_are_dropped_and_count_is_capped(): void
    {
        $this->claudeAnswers([
            ['title' => 'под игото', 'author' => 'Вазов', 'reason' => 'вече я имаш'],
            ['title' => 'Нова 1', 'author' => 'А', 'reason' => 'р'],
            ['title' => 'Нова 1', 'author' => 'А', 'reason' => 'дубликат'],
            ...array_map(fn ($i) => ['title' => "Друга {$i}", 'author' => 'Б', 'reason' => 'р'], range(1, 8)),
        ]);
        $user = $this->userWithReadBook('Под игото');

        $this->actingAs($user)->post('/recommendations');

        $titles = $user->recommendations()->pluck('title')->all();
        $this->assertCount(5, $titles);
        $this->assertNotContains('под игото', $titles);
        $this->assertSame(1, count(array_keys($titles, 'Нова 1')));
    }

    public function test_html_in_model_output_is_escaped(): void
    {
        $this->claudeAnswers([['title' => '<script>alert(1)</script>', 'author' => 'X', 'reason' => 'y']]);
        $user = $this->userWithReadBook();

        $this->actingAs($user)->post('/recommendations');

        $this->actingAs($user)->get('/dashboard')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_a_failure_shows_an_error_and_keeps_old_recommendations(): void
    {
        $this->mock(Claude::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('generateJson')->andThrow(new RuntimeException('API down')));
        $user = $this->userWithReadBook();
        Recommendation::create(['user_id' => $user->id, 'title' => 'Стара препоръка']);

        $this->actingAs($user)->post('/recommendations');

        $this->assertSame(['Стара препоръка'], $user->recommendations()->pluck('title')->all());
        $this->assertFalse(Cache::has(GenerateRecommendations::pendingKey($user->id)));
        $this->actingAs($user)->get('/dashboard')
            ->assertSee('не можаха да бъдат генерирани')
            ->assertSee('Стара препоръка');
    }

    public function test_an_empty_answer_is_handled(): void
    {
        $this->claudeAnswers([]);
        $user = $this->userWithReadBook();

        $this->actingAs($user)->post('/recommendations');

        $this->assertSame(0, $user->recommendations()->count());
        $this->actingAs($user)->get('/dashboard')->assertSee('Не успях да генерирам препоръки');
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
