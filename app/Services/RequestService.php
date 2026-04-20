<?php

namespace App\Services;

use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\User;

class RequestService
{
    const TRANSITIONS = [
        'pending'     => ['accepted', 'declined', 'cancelled'],
        'accepted'    => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed'   => ['returned'],
        'declined'    => [],
        'cancelled'   => [],
        'returned'    => [],
    ];

    public function transition(ExchangeRequest $request, string $newStatus, User $actor): void
    {
        $allowed = self::TRANSITIONS[$request->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            throw new \RuntimeException(
                "Cannot transition from '{$request->status}' to '{$newStatus}'."
            );
        }

        if ($newStatus === 'returned') {
            if ($request->resource_type !== 'item') {
                throw new \RuntimeException('Only item requests can be marked returned.');
            }
            $item = Item::find($request->resource_id);
            if (!$item || $item->offer_type !== 'lend') {
                throw new \RuntimeException('Only lend items can be marked returned.');
            }
            $request->update(['status' => 'returned']);
            $item->update(['is_available' => true]);
            return;
        }

        $request->update(['status' => $newStatus]);
    }

    public function confirmCompletion(ExchangeRequest $request, User $actor, CreditService $creditService): void
    {
        if ($actor->id === $request->requester_id) {
            $request->update(['requester_confirmed_at' => now()]);
        } elseif ($actor->id === $request->owner_id) {
            $request->update(['owner_confirmed_at' => now()]);
        } else {
            throw new \RuntimeException('User is not a party to this request.');
        }

        $request->refresh();

        if ($request->isBothConfirmed() && $request->status !== 'completed') {
            $creditService->transfer($request);
            $request->update(['status' => 'completed', 'completed_at' => now()]);

            if ($request->resource_type === 'item') {
                $item = Item::find($request->resource_id);
                if ($item) {
                    $item->update([
                        'is_available' => false,
                        'is_archived'  => $item->offer_type === 'gift',
                    ]);
                }
            }
        }
    }
}
