<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRequest extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'requester_id', 'owner_id', 'resource_type', 'resource_id',
        'proposed_datetime', 'duration_hours', 'message', 'status',
        'credit_type', 'credit_value',
        'requester_confirmed_at', 'owner_confirmed_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_datetime' => 'datetime',
            'requester_confirmed_at' => 'datetime',
            'owner_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'credit_value' => 'decimal:2',
            'duration_hours' => 'decimal:2',
        ];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function resource()
    {
        return $this->morphTo();
    }

    public function thread()
    {
        return $this->hasOne(Thread::class, 'request_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'request_id');
    }

    public function isBothConfirmed(): bool
    {
        return !is_null($this->requester_confirmed_at) && !is_null($this->owner_confirmed_at);
    }
}
