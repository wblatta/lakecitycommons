<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailabilitySchedule extends Model
{
    protected $fillable = [
        'resource_type', 'resource_id', 'recurrence',
        'day_of_week', 'specific_date', 'start_time', 'end_time', 'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'specific_date' => 'date',
            'is_blocked' => 'boolean',
            'day_of_week' => 'integer',
        ];
    }

    public function resource()
    {
        return $this->morphTo();
    }
}
