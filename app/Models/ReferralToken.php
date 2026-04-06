<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralToken extends Model
{
    protected $fillable = [
        'token', 'inviter_id', 'invitee_email', 'used_at', 'used_by_user_id', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    public function isValid(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture();
    }
}
