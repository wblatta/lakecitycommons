<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    use HasFactory;

    public const TYPES = ['rss', 'ics', 'html', 'dataset'];

    protected $fillable = [
        'name', 'url', 'type', 'selector_config', 'organization_id', 'active',
        'last_fetched_at', 'last_succeeded_at', 'consecutive_failures', 'failure_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'selector_config' => 'array',
            'active' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_succeeded_at' => 'datetime',
            'failure_notified_at' => 'datetime',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function contentItems()
    {
        return $this->hasMany(ContentItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
