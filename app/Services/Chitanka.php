<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Client for the Chitanka free Bulgarian library (https://chitanka.info/api).
 *
 * Search matches titles and author names; when an author matches, their books are listed too.
 */
class Chitanka
{
    /** Chitanka rejects queries shorter than this. */
    public const MIN_QUERY_LENGTH = 4;

    private const BASE_URL = 'https://chitanka.info';

    /**
     * Search by title or author.
     *
     * @return array<int, array<string, mixed>> Normalized results: the best-matching author's
     *                                          books first, then books and texts matching by title.
     */
    public function search(string $query, int $limit = 15): array
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $result = Cache::remember(
            'chitanka:search:'.md5(Str::lower($query)),
            $this->ttl(),
            fn () => $this->client()->get('/search.json', ['q' => $query])->throw()->json('result', []),
        );

        $authorBooks = [];

        foreach ([['book', $result['books'] ?? []], ['text', $result['texts'] ?? []]] as [$type, $rows]) {
            foreach ($rows as $row) {
                Cache::add('chitanka:item:'.$type.':'.$row['id'], $row, $this->ttl());
            }
        }

        if ($slug = $result['persons'][0]['slug'] ?? null) {
            $authorBooks = $this->booksOfPerson($slug);
        }

        $items = [
            ...$authorBooks,
            ...array_map(fn (array $b) => $this->map('book', $b), $result['books'] ?? []),
            ...array_map(fn (array $t) => $this->map('text', $t), $result['texts'] ?? []),
        ];

        // The same book can match by author and by title.
        $unique = [];
        foreach ($items as $item) {
            $unique[$item['type'].':'.$item['id']] ??= $item;
        }

        return array_slice(array_values($unique), 0, $limit);
    }

    /**
     * Books written by a person. A failure here only drops the author section.
     *
     * @return array<int, array<string, mixed>>
     */
    private function booksOfPerson(string $slug): array
    {
        try {
            $books = Cache::remember(
                'chitanka:person:'.$slug,
                $this->ttl(),
                // The full person payload lists every text (hundreds); keep only the books.
                fn () => $this->client()->get('/person/'.$slug.'.json')->throw()->json('books', []),
            );
        } catch (RequestException $e) {
            report($e);

            return [];
        }

        foreach ($books as $book) {
            Cache::add('chitanka:item:book:'.$book['id'], $book, $this->ttl());
        }

        return array_map(fn (array $b) => $this->map('book', $b), $books);
    }

    /**
     * Fetch a single book or text by its Chitanka id.
     *
     * @param  'book'|'text'  $type
     * @return array<string, mixed>|null Normalized item or null if not found.
     */
    public function find(string $type, int $id): ?array
    {
        if (! in_array($type, ['book', 'text'], true)) {
            return null;
        }

        $item = Cache::remember(
            'chitanka:item:'.$type.':'.$id,
            $this->ttl(),
            function () use ($type, $id) {
                $response = $this->client()->get('/'.$type.'/'.$id.'.json');

                return $response->status() === 404 ? null : $response->throw()->json($type);
            },
        );

        return $item ? $this->map($type, $item) : null;
    }

    /**
     * Split a stored id like "chitanka-book-1773" into [type, id], or null if it is not one.
     *
     * @return array{0: string, 1: int}|null
     */
    public static function parseExternalId(string $externalId): ?array
    {
        if (preg_match('/^chitanka-(book|text)-(\d+)$/', $externalId, $m)) {
            return [$m[1], (int) $m[2]];
        }

        return null;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 200, throw: false);
    }

    private function ttl(): int
    {
        return (int) config('services.chitanka.cache_ttl');
    }

    /**
     * @param  'book'|'text'  $type
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function map(string $type, array $item): array
    {
        $url = self::BASE_URL.'/'.$type.'/'.$item['id'].'-'.$item['slug'];

        $authors = collect($item['authors'] ?? [])->pluck('name')->filter()->values()->all();

        if (! $authors && ! empty($item['titleAuthor'])) {
            $authors = [$item['titleAuthor']];
        }

        return [
            // Column values for the books table.
            'google_id' => 'chitanka-'.$type.'-'.$item['id'],
            'source' => 'chitanka',
            'source_url' => $url,
            'published_date' => isset($item['year']) ? (string) $item['year'] : null,
            // Display data.
            'type' => $type,
            'id' => $item['id'],
            'title' => $item['title'] ?? '',
            'authors' => $authors,
            'year' => $item['year'] ?? null,
            'url' => $url,
            // e.g. ['epub' => 'https://chitanka.info/book/1773-pod-igoto.epub']
            'downloads' => collect($item['formats'] ?? [])
                ->mapWithKeys(fn (string $format) => [$format => $url.'.'.$format])
                ->all(),
        ];
    }
}
