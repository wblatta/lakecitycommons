<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'avatar', 'bio',
        'neighborhood_area', 'cross_streets', 'status', 'role', 'referred_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'time_bank_balance' => 'decimal:2',
        ];
    }

    public function avatarUrl(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return '';
    }

    public function initials(): string
    {
        $parts = explode(' ', trim($this->name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
        }
        return strtoupper(substr($this->name, 0, 2));
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function referralTokens()
    {
        return $this->hasMany(ReferralToken::class, 'inviter_id');
    }

    public function skills()
    {
        return $this->hasMany(Skill::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function sentRequests()
    {
        return $this->hasMany(ExchangeRequest::class, 'requester_id');
    }

    public function receivedRequests()
    {
        return $this->hasMany(ExchangeRequest::class, 'owner_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'to_user_id');
    }

    public function threadParticipations()
    {
        return $this->hasMany(ThreadParticipant::class);
    }

    public function waitlistEntries()
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
