<?php

namespace App\Jobs;

use App\Models\Book;
use App\Models\User;
use App\Services\Gemini;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class GenerateRecommendations implements ShouldQueue
{
    use Queueable;

    public const COUNT = 5;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public User $user) {}

    public static function pendingKey(int $userId): string
    {
        return 'recommendations:pending:'.$userId;
    }

    public static function errorKey(int $userId): string
    {
        return 'recommendations:error:'.$userId;
    }

    public function backoff(): int
    {
        return 20;
    }

    public function handle(Gemini $gemini): void
    {
        Cache::forget(self::errorKey($this->user->id));

        $read = $this->user->books()->wherePivot('status', 'read')
            ->orderByPivot('updated_at', 'desc')->limit(30)->get();

        if ($read->isEmpty()) {
            $this->finish('Добави поне една прочетена книга, за да получиш препоръки.');

            return;
        }

        $shelf = $this->user->books()->pluck('books.title');

        $answer = $gemini->generateJson($this->prompt($read, $shelf->all()), [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'author' => ['type' => 'string'],
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['title', 'author', 'reason'],
            ],
        ]);

        $owned = $shelf->map(fn (string $t) => Str::lower(trim($t)))->all();

        $recommendations = collect($answer)
            ->filter(fn ($r) => is_array($r) && is_string($r['title'] ?? null) && trim($r['title']) !== '')
            // The model may suggest a book the user already has, despite being told not to.
            ->reject(fn (array $r) => in_array(Str::lower(trim($r['title'])), $owned, true))
            ->unique(fn (array $r) => Str::lower(trim($r['title'])))
            ->take(self::COUNT)
            ->map(fn (array $r) => [
                'title' => Str::limit(trim($r['title']), 250, ''),
                'author' => isset($r['author']) && is_string($r['author']) ? Str::limit(trim($r['author']), 250, '') : null,
                'reason' => isset($r['reason']) && is_string($r['reason']) ? Str::limit(trim($r['reason']), 1000, '') : null,
            ])
            ->values();

        if ($recommendations->isEmpty()) {
            $this->finish('Не успях да генерирам препоръки този път. Опитай отново.');

            return;
        }

        // Replace the previous batch atomically so the dashboard never shows a half-empty list.
        DB::transaction(function () use ($recommendations) {
            $this->user->recommendations()->delete();
            $this->user->recommendations()->createMany($recommendations->all());
        });

        $this->finish();
    }

    public function failed(Throwable $exception): void
    {
        report($exception);

        $this->finish('Препоръките не можаха да бъдат генерирани в момента. Опитай отново след малко.');
    }

    /**
     * Clear the pending flag, optionally leaving an error message for the dashboard.
     */
    private function finish(?string $error = null): void
    {
        Cache::forget(self::pendingKey($this->user->id));

        if ($error) {
            Cache::put(self::errorKey($this->user->id), $error, now()->addHour());
        }
    }

    /**
     * @param  iterable<Book>  $read
     * @param  array<int, string>  $shelfTitles
     */
    private function prompt(iterable $read, array $shelfTitles): string
    {
        $lines = collect($read)->map(function ($book) {
            $authors = implode(', ', $book->authors ?? []);
            $rating = $book->pivot->rating ? ' — оценка '.$book->pivot->rating.'/5' : '';

            return '- '.Str::limit($book->title, 150, '').($authors ? ' ('.Str::limit($authors, 100, '').')' : '').$rating;
        })->implode("\n");

        $exclude = collect($shelfTitles)->map(fn (string $t) => '- '.Str::limit($t, 150, ''))->implode("\n");

        return <<<PROMPT
Ти си библиотекар и препоръчваш книги. Читателят е прочел следните книги (с негова оценка от 1 до 5, ако има):

{$lines}

Препоръчай точно 5 реално съществуващи книги, които вероятно ще му харесат. Давай предимно по-високо оценените като ориентир.
НЕ препоръчвай книги от този списък (вече ги има на рафтовете си):

{$exclude}

За всяка книга дай заглавие, автор и кратка причина (едно-две изречения) защо ѝ подхожда. Пиши на български; заглавието да е на български, ако книгата е издавана на български, иначе — оригиналното. Не измисляй несъществуващи книги.
PROMPT;
    }
}
