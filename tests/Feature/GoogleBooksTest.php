<?php

namespace Tests\Feature;

use App\Services\GoogleBooks;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function fakeVolume(string $id = 'abc123'): array
    {
        return [
            'id' => $id,
            'volumeInfo' => [
                'title' => 'Dune',
                'authors' => ['Frank Herbert'],
                'description' => 'Desert planet.',
                'pageCount' => 412,
                'publishedDate' => '1965',
                'industryIdentifiers' => [
                    ['type' => 'ISBN_10', 'identifier' => '0441013597'],
                    ['type' => 'ISBN_13', 'identifier' => '9780441013593'],
                ],
                'imageLinks' => ['thumbnail' => 'http://books.google.com/thumb.jpg'],
            ],
        ];
    }

    public function test_it_searches_and_maps_volumes(): void
    {
        Http::fake(['*/volumes?*' => Http::response(['items' => [$this->fakeVolume()]])]);

        $books = app(GoogleBooks::class)->search('dune');

        $this->assertCount(1, $books);
        $this->assertSame('abc123', $books[0]['google_id']);
        $this->assertSame('Dune', $books[0]['title']);
        $this->assertSame(['Frank Herbert'], $books[0]['authors']);
        $this->assertSame('https://books.google.com/thumb.jpg', $books[0]['thumbnail']);
        $this->assertSame('9780441013593', $books[0]['isbn']);
        $this->assertSame(412, $books[0]['page_count']);
        $this->assertSame('1965', $books[0]['published_date']);
    }

    public function test_it_caches_search_responses(): void
    {
        Http::fake(['*/volumes?*' => Http::response(['items' => [$this->fakeVolume()]])]);

        app(GoogleBooks::class)->search('dune');
        app(GoogleBooks::class)->search('Dune ');

        Http::assertSentCount(1);
    }

    public function test_it_returns_an_empty_list_when_nothing_is_found(): void
    {
        Http::fake(['*/volumes?*' => Http::response(['totalItems' => 0])]);

        $this->assertSame([], app(GoogleBooks::class)->search('zzzzqqq'));
    }

    public function test_it_finds_a_volume_by_id(): void
    {
        Http::fake(['*/volumes/abc123*' => Http::response($this->fakeVolume())]);

        $this->assertSame('Dune', app(GoogleBooks::class)->find('abc123')['title']);
    }

    public function test_it_returns_null_for_an_unknown_volume(): void
    {
        Http::fake(['*/volumes/nope*' => Http::response([], 404)]);

        $this->assertNull(app(GoogleBooks::class)->find('nope'));
    }

    public function test_it_sends_the_api_key_when_configured(): void
    {
        config(['services.google_books.key' => 'secret-key']);
        Http::fake(['*' => Http::response(['items' => []])]);

        app(GoogleBooks::class)->search('dune');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'key=secret-key'));
    }

    public function test_language_filter_is_sent_and_language_is_mapped(): void
    {
        $volume = $this->fakeVolume();
        $volume['volumeInfo']['language'] = 'bg';
        Http::fake(['*/volumes?*' => Http::response(['items' => [$volume]])]);

        $books = app(GoogleBooks::class)->search('dune', language: 'bg');

        $this->assertSame('bg', $books[0]['language']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'langRestrict=bg'));
    }

    public function test_searches_in_different_languages_are_cached_separately(): void
    {
        Http::fake(['*/volumes?*' => Http::response(['items' => []])]);

        app(GoogleBooks::class)->search('dune', language: 'bg');
        app(GoogleBooks::class)->search('dune', language: 'ru');
        app(GoogleBooks::class)->search('dune');

        Http::assertSentCount(3);
    }
}
