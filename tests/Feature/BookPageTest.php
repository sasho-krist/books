<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookPageTest extends TestCase
{
    use RefreshDatabase;

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
        $this->get('/books/'.$this->book()->id)->assertRedirect('/login');
    }

    public function test_details_are_shown_and_html_is_stripped(): void
    {
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
        $this->actingAs(User::factory()->create())->get('/books/999')->assertNotFound();
    }

    public function test_google_books_link_is_always_shown(): void
    {
        $book = $this->book();

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertSee('https://books.google.com/books?id=abc123', false)
            ->assertSee('Отвори в Google Books')
            ->assertDontSee('output=embed', false);
    }

    public function test_embedded_reader_is_shown_when_a_preview_exists(): void
    {
        $book = $this->book(['viewability' => 'PARTIAL', 'embeddable' => true]);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertSee('output=embed', false)
            ->assertSee('Откъс, предоставен от издателя');
    }

    public function test_reader_is_hidden_when_not_embeddable(): void
    {
        $book = $this->book(['viewability' => 'PARTIAL', 'embeddable' => false]);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertDontSee('output=embed', false);
    }

    public function test_missing_access_info_is_backfilled_from_google(): void
    {
        Cache::flush();
        Http::fake(['*/volumes/abc123*' => Http::response([
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
        Http::fake(['*' => Http::response([], 429)]);
        $book = $this->book(['viewability' => null]);

        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)
            ->assertOk()
            ->assertSee('Dune');
    }
}
