<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookPageTest extends TestCase
{
    use RefreshDatabase;

    private function book(): Book
    {
        return Book::create([
            'google_id' => 'abc123',
            'title' => 'Dune',
            'authors' => ['Frank Herbert'],
            'isbn' => '9780441013593',
            'page_count' => 412,
            'published_date' => '1965',
            'description' => '<p>Desert <b>planet</b>.</p>',
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
}
