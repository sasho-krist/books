<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MyBooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function volume(string $id = 'abc123'): array
    {
        return ['id' => $id, 'volumeInfo' => ['title' => 'Dune', 'authors' => ['Frank Herbert']]];
    }

    private function book(string $googleId = 'abc123'): Book
    {
        return Book::create(['google_id' => $googleId, 'title' => 'Dune', 'authors' => ['Frank Herbert']]);
    }

    public function test_guests_cannot_use_shelves(): void
    {
        $this->get('/my-books')->assertRedirect('/login');
        $this->post('/my-books', [])->assertRedirect('/login');
    }

    public function test_adding_creates_the_book_once_and_attaches_it(): void
    {
        Http::fake(['*/volumes/abc123*' => Http::response($this->volume())]);
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->post('/my-books', ['google_id' => 'abc123', 'status' => 'want'])
            ->assertSessionHas('status');
        $this->actingAs($bob)->post('/my-books', ['google_id' => 'abc123', 'status' => 'read']);

        $this->assertSame(1, Book::count());
        $this->assertSame('want', $alice->books()->first()->pivot->status);
        $this->assertSame('read', $bob->books()->first()->pivot->status);
        Http::assertSentCount(1);
    }

    public function test_adding_from_search_results_needs_no_extra_api_call(): void
    {
        Http::fake(['*/volumes?*' => Http::response(['items' => [$this->volume()]])]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/search?q=dune')->assertSee('Добави');
        $this->actingAs($user)->post('/my-books', ['google_id' => 'abc123', 'status' => 'reading']);

        Http::assertSentCount(1);
        $this->assertSame('reading', $user->books()->first()->pivot->status);
    }

    public function test_adding_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $this->book();

        $this->actingAs($user)->post('/my-books', ['google_id' => 'abc123', 'status' => 'want']);
        $this->actingAs($user)->post('/my-books', ['google_id' => 'abc123', 'status' => 'want']);

        $this->assertSame(1, $user->books()->count());
    }

    public function test_unknown_google_id_shows_an_error(): void
    {
        Http::fake(['*' => Http::response([], 404)]);

        $this->actingAs(User::factory()->create())
            ->post('/my-books', ['google_id' => 'nope', 'status' => 'want'])
            ->assertSessionHas('error');

        $this->assertSame(0, Book::count());
    }

    public function test_add_validates_input(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/my-books', ['google_id' => 'a b/c', 'status' => 'bogus'])
            ->assertSessionHasErrors(['google_id', 'status']);
    }

    public function test_tabs_filter_by_status(): void
    {
        $user = User::factory()->create();
        $user->books()->attach($this->book('a')->id, ['status' => 'reading']);
        $other = Book::create(['google_id' => 'b', 'title' => 'Emma']);
        $user->books()->attach($other->id, ['status' => 'read']);

        $this->actingAs($user)->get('/my-books?status=read')
            ->assertSee('Emma')->assertDontSee('Dune');
        $this->actingAs($user)->get('/my-books')
            ->assertSee('Dune')->assertDontSee('Emma');
    }

    public function test_status_rating_and_notes_can_be_updated(): void
    {
        $user = User::factory()->create();
        $book = $this->book();
        $user->books()->attach($book->id, ['status' => 'reading']);

        $this->actingAs($user)->patch("/my-books/{$book->id}", [
            'status' => 'read', 'rating' => 5, 'notes' => 'Great',
        ])->assertSessionHasNoErrors();

        $pivot = $user->books()->first()->pivot;
        $this->assertSame('read', $pivot->status);
        $this->assertSame(5, $pivot->rating);
        $this->assertSame('Great', $pivot->notes);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $user = User::factory()->create();
        $book = $this->book();
        $user->books()->attach($book->id, ['status' => 'read']);

        $this->actingAs($user)->patch("/my-books/{$book->id}", ['rating' => 6])
            ->assertSessionHasErrors('rating');
    }

    public function test_cannot_update_a_book_that_is_not_on_your_shelf(): void
    {
        $book = $this->book();
        $owner = User::factory()->create();
        $owner->books()->attach($book->id, ['status' => 'read']);

        $this->actingAs(User::factory()->create())
            ->patch("/my-books/{$book->id}", ['status' => 'want'])
            ->assertNotFound();

        $this->assertSame('read', $owner->books()->first()->pivot->status);
    }

    public function test_book_can_be_removed_from_shelves(): void
    {
        $user = User::factory()->create();
        $book = $this->book();
        $user->books()->attach($book->id, ['status' => 'read']);

        $this->actingAs($user)->delete("/my-books/{$book->id}");

        $this->assertSame(0, $user->books()->count());
        $this->assertSame(1, Book::count());
    }
}
