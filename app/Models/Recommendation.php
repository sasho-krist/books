<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    protected $fillable = ['user_id', 'title', 'author', 'reason'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Search page URL to find this recommendation.
     */
    public function searchUrl(): string
    {
        return route('search', ['q' => trim($this->title.' '.$this->author)]);
    }
}
