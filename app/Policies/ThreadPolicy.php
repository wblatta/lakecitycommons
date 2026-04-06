<?php

namespace App\Policies;

use App\Models\Thread;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ThreadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function view(User $user, Thread $thread): bool
    {
        return $thread->participants()->where('user_id', $user->id)->exists();
    }
}
