<?php

namespace App\Policies;

use App\Models\ExchangeRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExchangeRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function view(User $user, ExchangeRequest $exchangeRequest): bool
    {
        return $user->id === $exchangeRequest->requester_id || $user->id === $exchangeRequest->owner_id;
    }
}
