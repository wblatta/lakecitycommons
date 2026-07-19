<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'submitter_name', 'submitter_email', 'title', 'body',
        'event_fields', 'status', 'ip_hash',
    ];

    protected function casts(): array
    {
        return ['event_fields' => 'array'];
    }
}
