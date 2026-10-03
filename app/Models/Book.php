<?php

namespace App\Models;

use App\Services\Chitanka;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_id',
        'source',
        'source_url',
        'downloads',
        'title',
        'authors',
        'thumbnail',
        'isbn',
        'description',
        'page_count',
        'published_date',
        'language',
        'viewability',
        'embeddable',
    ];

    /**
     * Human-readable name for an ISO 639-1 language code.
     */
    public static function languageLabel(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return match (strtolower($code)) {
            'bg' => 'Български',
            'en' => 'Английски',
            'ru' => 'Руски',
            'de' => 'Немски',
            'fr' => 'Френски',
            default => strtoupper($code),
        };
    }

    public function isChitanka(): bool
    {
        return $this->source === 'chitanka';
    }

    /**
     * Whether Google offers an embeddable preview (full or partial text).
     */
    public function hasPreview(): bool
    {
        return ! $this->isChitanka() && $this->embeddable && in_array($this->viewability, ['PARTIAL', 'ALL_PAGES'], true);
    }

    /**
     * Link to Chitanka's search (free Bulgarian library) for this title.
     */
    public function chitankaSearchUrl(): string
    {
        // Title only: author names are spelled differently across sources and break the search.
        $queries = Chitanka::titleQueries($this->title);

        return 'https://chitanka.info/search?'.http_build_query(['q' => end($queries) ?: $this->title]);
    }

    public function googleBooksUrl(): string
    {
        return 'https://books.google.com/books?id='.urlencode($this->google_id);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['status', 'rating', 'notes'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'embeddable' => 'boolean',
            'downloads' => 'array',
        ];
    }
}
