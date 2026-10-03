<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    protected function casts(): array
    {
        return [
            'authors' => 'array',
        ];
    }
}
