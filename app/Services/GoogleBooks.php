<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleBooks
{
    /**
     * Search volumes by free-text query.
     *
     * @return array<int, array<string, mixed>> Normalized books.
     */
    public function search(string $query, int $maxResults = 20): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $cacheKey = 'google_books:search:'.md5(Str::lower($query).'|'.$maxResults);

        $items = Cache::remember($cacheKey, $this->ttl(), function () use ($query, $maxResults) {
            $response = $this->client()->get('/volumes', [
                'q' => $query,
                'maxResults' => $maxResults,
                'printType' => 'books',
            ])->throw();

            return $response->json('items', []);
        });

        // Warm the per-volume cache so adding a book from results needs no extra API call.
        foreach ($items as $item) {
            Cache::add('google_books:volume:'.$item['id'], $item, $this->ttl());
        }

        return array_map(fn (array $item) => $this->map($item), $items);
    }

    /**
     * Fetch a single volume by its Google Books id.
     *
     * @return array<string, mixed>|null Normalized book or null if not found.
     */
    public function find(string $googleId): ?array
    {
        $cacheKey = 'google_books:volume:'.$googleId;

        $item = Cache::remember($cacheKey, $this->ttl(), function () use ($googleId) {
            $response = $this->client()->get('/volumes/'.urlencode($googleId));

            if ($response->status() === 404) {
                return null;
            }

            return $response->throw()->json();
        });

        return $item ? $this->map($item) : null;
    }

    /**
     * Map a raw Google Books volume to the shape used by the app.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function map(array $item): array
    {
        $info = $item['volumeInfo'] ?? [];

        $isbn = collect($info['industryIdentifiers'] ?? [])
            ->firstWhere('type', 'ISBN_13')['identifier'] ?? null;

        $thumbnail = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null;

        return [
            'google_id' => $item['id'],
            'title' => $info['title'] ?? '',
            'authors' => $info['authors'] ?? [],
            'thumbnail' => $thumbnail ? preg_replace('/^http:/', 'https:', $thumbnail) : null,
            'isbn' => $isbn,
            'description' => $info['description'] ?? null,
            'page_count' => $info['pageCount'] ?? null,
            'published_date' => $info['publishedDate'] ?? null,
        ];
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl(config('services.google_books.base_url'))
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 200, throw: false);

        if ($key = config('services.google_books.key')) {
            $client = $client->withQueryParameters(['key' => $key]);
        }

        return $client;
    }

    private function ttl(): int
    {
        return (int) config('services.google_books.cache_ttl');
    }
}
