<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\Chitanka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fake HTTP with the given stubs; Chitanka returns nothing unless a stub says otherwise
     * (the first matching stub wins, so the default must come last).
     */
    private function fakeHttp(array $stubs): void
    {
        Http::fake($stubs + ['chitanka.info/*' => Http::response(['result' => []])]);
    }

    private function book(array $attributes = []): Book
    {
        return Book::create($attributes + [
            'google_id' => 'abc123',
            'title' => 'Dune',
            'authors' => ['Frank Herbert'],
            'isbn' => '9780441013593',
            'page_count' => 412,
            'published_date' => '1965',
            'description' => '<p>Desert <b>planet</b>.</p>',
            'viewability' => 'NO_PAGES',
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->fakeHttp([]);
        $this->get('/books/'.$this->book()->id)->assertRedirect('/login');
    }

    public function test_details_are_shown_and_html_is_stripped(): void
    {
        $this->fakeHttp([]);
        $this->actingAs(User::factory()->create())
            ->get('/books/'.$this->book()->id)
            ->assertOk()
            ->assertSee('Dune')
            ->assertSee('Frank Herbert')
            ->assertSee('9780441013593')
            ->assertSee('412')
            ->assertSee('Desert planet.')
            ->assertDontSee('<b>', false)
            ->assertSee('Добави към рафтовете си');
    }

    public function test_shelf_form_is_shown_when_the_book_is_on_a_shelf(): void
    {
        $this->fakeHttp([]);
        $user = User::factory()->create();
        $book = $this->book();
        $user->books()->attach($book->id, ['status' => 'read', 'notes' => 'Loved it']);

        $this->actingAs($user)->get('/books/'.$book->id)
            ->assertOk()
            ->assertSee('На твоя рафт')
            ->assertSee('Loved it');
    }

    public function test_unknown_book_returns_404(): void
    {
        $this->fakeHttp([]);
        $this->actingAs(User::factory()->create())->get('/books/999')->assertNotFound();
    }

    public function test_google_books_link_is_always_shown(): void
    {
        $this->fakeHttp([]);
        $book = $this->book();

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertSee('https://books.google.com/books?id=abc123', false)
            ->assertSee('Отвори в Google Books')
            ->assertSee('https://chitanka.info/search?q=Dune', false)
            ->assertDontSee('output=embed', false);
    }

    public function test_embedded_reader_is_shown_when_a_preview_exists(): void
    {
        $this->fakeHttp([]);
        $book = $this->book(['viewability' => 'PARTIAL', 'embeddable' => true]);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertSee('output=embed', false)
            ->assertSee('Откъс, предоставен от издателя');
    }

    public function test_reader_is_hidden_when_not_embeddable(): void
    {
        $this->fakeHttp([]);
        $book = $this->book(['viewability' => 'PARTIAL', 'embeddable' => false]);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertDontSee('output=embed', false);
    }

    public function test_missing_access_info_is_backfilled_from_google(): void
    {
        Cache::flush();
        $this->fakeHttp(['*/volumes/abc123*' => Http::response([
            'id' => 'abc123',
            'volumeInfo' => ['title' => 'Dune'],
            'accessInfo' => ['viewability' => 'ALL_PAGES', 'embeddable' => true],
        ])]);
        $book = $this->book(['viewability' => null]);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertSee('output=embed', false)
            ->assertSee('Пълен текст');

        $this->assertSame('ALL_PAGES', $book->fresh()->viewability);
    }

    public function test_page_still_loads_when_backfill_fails(): void
    {
        Cache::flush();
        $this->fakeHttp(['*' => Http::response([], 429)]);
        $book = $this->book(['viewability' => null]);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertOk()
            ->assertSee('Dune');
    }

    private function lupinMatch(): array
    {
        return ['result' => ['books' => [[
            'id' => 234, 'slug' => 'arsen-ljupen-kradetsyt-dzhentylmen',
            'title' => 'Арсен Люпен — крадецът джентълмен', 'titleAuthor' => 'Морис Льоблан',
            'year' => 1907, 'formats' => ['epub'], 'hasCover' => true, 'cover' => 'thumb/book-cover/00/234.600.jpg',
            'authors' => [['name' => 'Морис Льоблан']],
        ]]]];
    }

    public function test_title_queries_handle_chitankas_long_dash(): void
    {
        $this->assertSame(
            ['Арсен Люпен — крадецът джентълмен', 'Арсен Люпен'],
            Chitanka::titleQueries('Арсен Люпен - крадецът джентълмен'),
        );
        $this->assertSame(['Dune'], Chitanka::titleQueries('Dune'));
        $this->assertSame([], Chitanka::titleQueries('abc'));
    }

    public function test_google_book_page_offers_matching_chitanka_editions(): void
    {
        $this->fakeHttp(['chitanka.info/search.json*' => Http::response($this->lupinMatch())]);
        $book = $this->book([
            'title' => 'Арсен Люпен - крадецът джентълмен',
            'authors' => ['Морис Ляоблан'], // Google's spelling differs from Chitanka's; must not matter
        ]);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertOk()
            ->assertSee('Налична в Читанка')
            ->assertSee('Морис Льоблан')
            ->assertSee('/chitanka/book/234/read', false)
            ->assertSee('https://assets2.chitanka.info/thumb/book-cover/00/234.600.jpg', false);

        // Dashed title is retried with Chitanka's long dash, without the author.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'search.json') && str_contains(urldecode($r->url()), 'q=Арсен Люпен — крадецът джентълмен'));
        Http::assertNotSent(fn ($r) => str_contains(urldecode($r->url()), 'Ляоблан'));
    }

    public function test_chitanka_search_link_uses_the_title_only(): void
    {
        $book = $this->book(['title' => 'Арсен Люпен - крадецът джентълмен', 'authors' => ['Морис Ляоблан']]);

        $this->assertStringContainsString('q='.urlencode('Арсен Люпен'), $book->chitankaSearchUrl());
        $this->assertStringNotContainsString(urlencode('Ляоблан'), $book->chitankaSearchUrl());
    }

    public function test_opening_a_chitanka_edition_saves_it_and_redirects_to_the_reader(): void
    {
        $this->fakeHttp(['chitanka.info/book/234.json' => Http::response(['book' => $this->lupinMatch()['result']['books'][0]])]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/chitanka/book/234/read');

        $book = Book::firstWhere('google_id', 'chitanka-book-234');
        $this->assertNotNull($book);
        $response->assertRedirect("/books/{$book->id}/read");
        $this->assertSame(0, $user->books()->count(), 'opening must not put the book on a shelf');

        $this->actingAs($user)->post('/chitanka/book/234/read');
        $this->assertSame(1, Book::where('google_id', 'chitanka-book-234')->count());
    }

    public function test_opening_validates_the_type_and_missing_books(): void
    {
        $this->fakeHttp(['chitanka.info/*' => Http::response([], 404)]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/chitanka/movie/1/read')->assertNotFound();
        $this->actingAs($user)->post('/chitanka/book/999999/read')->assertNotFound();
    }

    public function test_guests_cannot_open_chitanka_editions(): void
    {
        $this->post('/chitanka/book/1/read')->assertRedirect('/login');
    }

    public function test_missing_chitanka_cover_is_backfilled(): void
    {
        $this->fakeHttp(['chitanka.info/book/234.json' => Http::response(['book' => $this->lupinMatch()['result']['books'][0]])]);
        $book = Book::create([
            'google_id' => 'chitanka-book-234', 'source' => 'chitanka', 'title' => 'Арсен Люпен',
            'source_url' => 'https://chitanka.info/book/234-arsen-ljupen-kradetsyt-dzhentylmen',
        ]);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)->assertOk();

        $this->assertSame('https://assets2.chitanka.info/thumb/book-cover/00/234.600.jpg', $book->fresh()->thumbnail);
    }

    public function test_related_queries_drop_leading_words_but_never_start_with_a_preposition(): void
    {
        $this->assertSame(
            ['престъпления на Арсен Люпен', 'Арсен Люпен'],
            Chitanka::relatedQueries('Трите престъпления на Арсен Люпен'),
        );
        $this->assertSame([], Chitanka::relatedQueries('Dune'));
        $this->assertSame([], Chitanka::relatedQueries('Дюн'));
    }

    public function test_when_the_title_is_not_in_chitanka_related_books_are_offered_honestly(): void
    {
        $this->fakeHttp([
            // Only the shortened query finds anything.
            'chitanka.info/search.json*' => fn ($request) => str_ends_with(urldecode($request->url()), 'q=Арсен Люпен')
                ? Http::response($this->lupinMatch())
                : Http::response(['result' => []]),
        ]);
        $book = $this->book(['title' => 'Трите престъпления на Арсен Люпен', 'authors' => ['М Ляоблан']]);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertOk()
            ->assertSee('Сродни книги в Читанка')
            ->assertDontSee('Налична в Читанка')
            ->assertSee('/chitanka/book/234/read', false)
            // The button goes to the query that works, not to the empty exact-title search.
            ->assertSee('https://chitanka.info/search?q=%D0%90%D1%80%D1%81%D0%B5%D0%BD+%D0%9B%D1%8E%D0%BF%D0%B5%D0%BD', false);
    }

    public function test_exact_matches_are_labelled_as_available(): void
    {
        $this->fakeHttp(['chitanka.info/search.json*' => Http::response($this->lupinMatch())]);
        $book = $this->book(['title' => 'Арсен Люпен - крадецът джентълмен']);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertSee('Налична в Читанка')
            ->assertDontSee('Сродни книги в Читанка');
    }
}
