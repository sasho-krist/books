<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
     * Query variants to try for a title from another source. Chitanka matches by prefix and only
     * knows the long dash ("—"), so "A - B" fails while "A — B" and "A" succeed.
     *
     * @return array<int, string>
     */
    public static function titleQueries(string $title): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        $normalized = preg_replace('/\s[-–]\s/u', ' — ', $title);

        // The part before the first separator is usually the main title.
        $head = trim(preg_split('/\s[—–-]\s|:/u', $title)[0]);

        return array_values(array_unique(array_filter(
            [$normalized, $head],
            fn (string $q) => mb_strlen($q) >= self::MIN_QUERY_LENGTH,
        )));
    }

    /**
     * Find the Chitanka entries that match a title (from Google Books, for example).
     * Authors are deliberately ignored: other sources transliterate names differently.
     *
     * @return array<int, array<string, mixed>>
     */
    public function matchTitle(string $title, int $limit = 5): array
    {
        foreach (self::titleQueries($title) as $query) {
            $results = $this->search($query, $limit);

            if ($results !== []) {
                return $results;
            }
        }

        return [];
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
     * Plain text of a book or text, downloaded once and kept on disk (Chitanka rate-limits requests).
     *
     * @param  'book'|'text'  $type
     *
     * @throws RequestException when the download fails
     */
    public function fullText(string $type, int $id, string $slug): string
    {
        $path = 'chitanka/'.$type.'-'.$id.'.txt';
        $disk = Storage::disk('local');

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        $response = Http::withUserAgent('MoqtaBiblioteka/1.0 (portfolio project)')
            ->timeout(60)
            ->retry(2, 1000, throw: false)
            ->get(self::BASE_URL.'/'.$type.'/'.$id.'-'.$slug.'.txt')
            ->throw();

        // Books are served as text/html even though the body is plain text, so check the content.
        $text = preg_replace('/^﻿/', '', $response->body());

        if (trim($text) === '' || preg_match('/^\s*<(!doctype|html)/i', $text) || ! mb_check_encoding($text, 'UTF-8')) {
            throw new RequestException($response);
        }

        $disk->put($path, $text);

        return $text;
    }

    /**
     * Split text into pages of roughly $target characters, breaking only between lines.
     *
     * @return array<int, string>
     */
    public static function paginate(string $text, int $target = 4500): array
    {
        $pages = [];
        $current = '';

        foreach (preg_split('/\R/u', $text) as $line) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($line) > $target) {
                $pages[] = $current;
                $current = '';
            }

            $current .= $line.'
';
        }

        if (trim($current) !== '') {
            $pages[] = $current;
        }

        return $pages;
    }

    /**
     * Chitanka's plain text has no markup: guess chapter titles as short lines without sentence punctuation.
     */
    public static function looksLikeHeading(string $line): bool
    {
        $line = trim($line);

        return $line !== '' && mb_strlen($line) <= 70 && ! preg_match('/[.!?…,;:"»“„)]$/u', $line);
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
            ->withUserAgent('MoqtaBiblioteka/1.0 (portfolio project)')
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
            'thumbnail' => ! empty($item['hasCover']) && ! empty($item['cover'])
                ? 'https://assets2.chitanka.info/'.ltrim($item['cover'], '/')
                : null,
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
