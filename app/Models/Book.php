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
    ];

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
        ];
    }
}
