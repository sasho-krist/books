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

    /**
     * Fake HTTP with the given stubs; Chitanka returns nothing unless a stub says otherwise
     * (the first matching stub wins, so the default must come last).
     */
    private function fakeHttp(array $stubs): void
    {
        Http::fake($stubs + ['chitanka.info/*' => Http::response(['result' => []])]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/search')->assertRedirect('/login');
    }

    public function test_search_page_renders_without_query(): void
    {
        $this->fakeHttp([]);

        $this->actingAs(User::factory()->create())
            ->get('/search')
            ->assertOk()
            ->assertSee('Търсене на книги');

        Http::assertNothingSent();
    }

    public function test_results_are_displayed(): void
    {
        $this->fakeHttp(['*/volumes?*' => Http::response(['items' => [[
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
        $this->fakeHttp(['*/volumes?*' => Http::response(['totalItems' => 0])]);

        $this->actingAs(User::factory()->create())
            ->get('/search?q=zzzzqqq')
            ->assertOk()
            ->assertSee('Няма намерени книги');
    }

    public function test_api_failure_shows_an_error_instead_of_crashing(): void
    {
        $this->fakeHttp(['*/volumes?*' => Http::response(['error' => 'quota'], 429)]);

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

    private function chitankaResult(): array
    {
        return ['result' => [
            'books' => [[
                'id' => 1773, 'slug' => 'pod-igoto', 'title' => 'Под игото', 'year' => 1894,
                'authors' => [['name' => 'Иван Вазов']], 'formats' => ['fb2.zip', 'epub', 'txt.zip'],
            ]],
            'texts' => [],
        ]];
    }

    public function test_chitanka_results_are_shown_with_read_and_download_links(): void
    {
        $this->fakeHttp(['chitanka.info/*' => Http::response($this->chitankaResult())]);

        $this->actingAs(User::factory()->create())
            ->get('/search?q='.urlencode('Под игото'))
            ->assertOk()
            ->assertSee('Читанка')
            ->assertSee('Иван Вазов')
            ->assertSee('https://chitanka.info/book/1773-pod-igoto', false)
            ->assertSee('https://chitanka.info/book/1773-pod-igoto.epub', false)
            ->assertSee('Чети онлайн');
    }

    public function test_chitanka_results_are_cached(): void
    {
        $this->fakeHttp([
            'chitanka.info/*' => Http::response($this->chitankaResult()),
            '*/volumes?*' => Http::response(['totalItems' => 0]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/search?q='.urlencode('Под игото'));
        $this->actingAs($user)->get('/search?q='.urlencode('под игото'));

        Http::assertSentCount(2); // one Google + one Chitanka request; the repeat is served from cache
    }

    public function test_chitanka_failure_does_not_hide_google_results(): void
    {
        $this->fakeHttp([
            'chitanka.info/*' => Http::response([], 500),
            '*/volumes?*' => Http::response(['items' => [[
                'id' => 'abc123', 'volumeInfo' => ['title' => 'Дюн'],
            ]]]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/search?q=dune')
            ->assertOk()
            ->assertSee('Дюн')
            ->assertSee('Търсенето в Читанка не е достъпно');
    }

    public function test_short_queries_skip_chitanka(): void
    {
        $this->fakeHttp(['*/volumes?*' => Http::response(['totalItems' => 0])]);

        $this->actingAs(User::factory()->create())
            ->get('/search?q=abc')
            ->assertSee('поне 4 символа');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'chitanka.info'));
    }

    public function test_chitanka_search_by_author_lists_the_authors_books(): void
    {
        $this->fakeHttp([
            'chitanka.info/search.json*' => Http::response(['result' => [
                'persons' => [['slug' => 'ivan-vazov', 'name' => 'Иван Вазов']],
                'texts' => [], 'books' => [],
            ]]),
            'chitanka.info/person/ivan-vazov.json' => Http::response([
                'texts_as_author' => [['id' => 1, 'slug' => 'ignored', 'title' => 'Не се показва']],
                'books' => [[
                    'id' => 1775, 'slug' => 'v-nedrata-na-rodopite', 'title' => 'В недрата на Родопите',
                    'titleAuthor' => 'Иван Вазов', 'year' => 1892, 'formats' => ['epub'],
                ]],
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/search?q='.urlencode('Вазов'))
            ->assertOk()
            ->assertSee('В недрата на Родопите')
            ->assertSee('Иван Вазов')
            ->assertSee('https://chitanka.info/book/1775-v-nedrata-na-rodopite.epub', false)
            ->assertDontSee('Не се показва');
    }

    public function test_author_books_failure_keeps_title_matches(): void
    {
        $this->fakeHttp([
            'chitanka.info/search.json*' => Http::response(['result' => [
                'persons' => [['slug' => 'ivan-vazov', 'name' => 'Иван Вазов']],
                'books' => [['id' => 1773, 'slug' => 'pod-igoto', 'title' => 'Под игото', 'formats' => []]],
            ]]),
            'chitanka.info/person/*' => Http::response([], 500),
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/search?q='.urlencode('Вазов'))
            ->assertOk()
            ->assertSee('Под игото');
    }

    public function test_same_book_matched_by_author_and_title_is_listed_once(): void
    {
        $book = ['id' => 1773, 'slug' => 'pod-igoto', 'title' => 'Под игото', 'formats' => []];
        $this->fakeHttp([
            'chitanka.info/search.json*' => Http::response(['result' => [
                'persons' => [['slug' => 'ivan-vazov']], 'books' => [$book],
            ]]),
            'chitanka.info/person/*' => Http::response(['books' => [$book]]),
        ]);

        $html = $this->actingAs(User::factory()->create())
            ->get('/search?q='.urlencode('Вазов'))
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Под игото</a>'));
    }

    public function test_language_filter_is_applied_and_badge_shown(): void
    {
        $this->fakeHttp(['*/volumes?*' => Http::response(['items' => [[
            'id' => 'ru1', 'volumeInfo' => ['title' => 'Люпен', 'language' => 'ru'],
        ]]])]);

        $this->actingAs(User::factory()->create())
            ->get('/search?q=lupin&lang=bg')
            ->assertOk()
            ->assertSee('Руски')
            ->assertSee('Всички езици');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'langRestrict=bg'));
    }

    public function test_unknown_language_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/search?q=lupin&lang=xx')
            ->assertSessionHasErrors('lang');
    }
}
