<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_id',
        'title',
        'authors',
        'thumbnail',
        'isbn',
        'description',
        'page_count',
        'published_date',
        'viewability',
        'embeddable',
    ];

    /**
     * Whether Google offers an embeddable preview (full or partial text).
     */
    public function hasPreview(): bool
    {
        return $this->embeddable && in_array($this->viewability, ['PARTIAL', 'ALL_PAGES'], true);
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
        ];
    }
}
