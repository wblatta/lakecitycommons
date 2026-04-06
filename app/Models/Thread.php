<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Thread extends Model
{
    protected $fillable = ['request_id', 'subject'];

    public function request()
    {
        return $this->belongsTo(ExchangeRequest::class, 'request_id');
    }

    public function participants()
    {
        return $this->hasMany(ThreadParticipant::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'thread_participants')->withPivot('last_read_at');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
