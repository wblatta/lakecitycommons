<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id', 'url', 'title', 'summary', 'content_hash',
        'kind', 'published_at', 'fetched_at', 'status',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'fetched_at' => 'datetime'];
    }

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('status', 'new');
    }
}
