<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'starts_at', 'ends_at', 'location',
        'url', 'organization_id', 'submission_id', 'status', 'source_id', 'external_uid',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
