<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\Chitanka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function chitankaBook(): Book
    {
        return Book::create([
            'google_id' => 'chitanka-book-234',
            'source' => 'chitanka',
            'title' => 'Арсен Люпен',
            'source_url' => 'https://chitanka.info/book/234-arsen-ljupen',
        ]);
    }

    /** Book with enough lines to need several pages. */
    private function longText(): string
    {
        $text = "Морис Льоблан\nАрсен Люпен\nI\nАрестуването\n";

        for ($i = 1; $i <= 60; $i++) {
            $text .= "Това е абзац номер {$i}. ".str_repeat('дума ', 40)."\n";
        }

        return $text;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/books/'.$this->chitankaBook()->id.'/read')->assertRedirect('/login');
    }

    public function test_google_books_cannot_be_read_here(): void
    {
        $book = Book::create(['google_id' => 'abc123', 'title' => 'Dune']);

        $this->actingAs(User::factory()->create())->get("/books/{$book->id}/read")->assertNotFound();
    }

    public function test_text_is_downloaded_once_then_paged_from_disk(): void
    {
        Http::fake(['chitanka.info/book/234-arsen-ljupen.txt' => Http::response($this->longText(), 200, ['Content-Type' => 'text/html; charset=UTF-8'])]);
        $user = User::factory()->create();
        $book = $this->chitankaBook();

        $first = $this->actingAs($user)->get("/books/{$book->id}/read");
        $first->assertOk()
            ->assertSee('Арестуването')
            ->assertSee('Това е абзац номер 1.')
            ->assertSee('Напред →')
            ->assertSee('Текстът е от');
        $this->assertMatchesRegularExpression('~<h3[^>]*>\s*I\s*</h3>~', $first->getContent());

        $this->actingAs($user)->get("/books/{$book->id}/read?page=2")
            ->assertOk()
            ->assertDontSee('Арестуването');

        Http::assertSentCount(1);
        Storage::disk('local')->assertExists('chitanka/book-234.txt');
    }

    public function test_page_number_is_clamped_to_the_last_page(): void
    {
        Http::fake(['chitanka.info/*' => Http::response($this->longText())]);
        $book = $this->chitankaBook();

        $this->actingAs(User::factory()->create())->get("/books/{$book->id}/read?page=9999")
            ->assertOk()
            ->assertSee('Това е абзац номер 60.');
    }

    public function test_invalid_page_is_rejected(): void
    {
        Http::fake(['chitanka.info/*' => Http::response($this->longText())]);
        $book = $this->chitankaBook();

        $this->actingAs(User::factory()->create())->get("/books/{$book->id}/read?page=abc")
            ->assertSessionHasErrors('page');
    }

    public function test_text_is_escaped(): void
    {
        Http::fake(['chitanka.info/*' => Http::response("Заглавие\nТекст <script>alert(1)</script> край.\n")]);
        $book = $this->chitankaBook();

        $this->actingAs(User::factory()->create())->get("/books/{$book->id}/read")
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_html_error_page_is_not_saved_as_text(): void
    {
        Http::fake(['chitanka.info/*' => Http::response('<!DOCTYPE html><html>error 1015</html>')]);
        $book = $this->chitankaBook();

        $this->actingAs(User::factory()->create())->get("/books/{$book->id}/read")
            ->assertRedirect("/books/{$book->id}")
            ->assertSessionHas('error');

        Storage::disk('local')->assertMissing('chitanka/book-234.txt');
    }

    public function test_download_failure_redirects_back_with_an_error(): void
    {
        Http::fake(['chitanka.info/*' => Http::response('', 429)]);
        $book = $this->chitankaBook();

        $this->actingAs(User::factory()->create())->get("/books/{$book->id}/read")
            ->assertRedirect("/books/{$book->id}")
            ->assertSessionHas('error');
    }

    public function test_paginate_breaks_between_lines_only(): void
    {
        $pages = Chitanka::paginate("aaaa\nbbbb\ncccc\ndddd", 9);

        $this->assertSame(["aaaa\nbbbb\n", "cccc\ndddd\n"], $pages);
        $this->assertSame([], Chitanka::paginate("  \n \n"));
    }

    public function test_heading_heuristic(): void
    {
        $this->assertTrue(Chitanka::looksLikeHeading('Арестуването на Арсен Люпен'));
        $this->assertFalse(Chitanka::looksLikeHeading('Кратко изречение.'));
        $this->assertFalse(Chitanka::looksLikeHeading(str_repeat('дума ', 30)));
    }
}
