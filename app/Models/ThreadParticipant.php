<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThreadParticipant extends Model
{
    protected $fillable = ['thread_id', 'user_id', 'last_read_at'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    public function thread()
    {
        return $this->belongsTo(Thread::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
