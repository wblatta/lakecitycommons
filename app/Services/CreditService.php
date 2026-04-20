<?php

namespace App\Services;

use App\Models\ExchangeRequest;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CreditService
{
    const GRACE_THRESHOLD = -5.00;

    public function calculateCreditValue(string $creditType, ?float $customValue, float $durationHours = 1.0): float
    {
        return match ($creditType) {
            'gift' => 0.0,
            'time_equal' => $durationHours,
            'custom' => (float) $customValue,
        };
    }

    public function canAfford(int $userId, float $amount): bool
    {
        $balance = DB::table('users')->where('id', $userId)->value('time_bank_balance');
        return ($balance - $amount) >= self::GRACE_THRESHOLD;
    }

    public function transfer(ExchangeRequest $request): void
    {
        if ($request->credit_type === 'gift') {
            return;
        }

        DB::transaction(function () use ($request) {
            $amount = (float) $request->credit_value;

            $balance = DB::table('users')
                ->where('id', $request->requester_id)
                ->lockForUpdate()
                ->value('time_bank_balance');

            if (($balance - $amount) < self::GRACE_THRESHOLD) {
                throw new \RuntimeException('Insufficient balance for credit transfer.');
            }

            Transaction::create([
                'request_id' => $request->id,
                'from_user_id' => $request->requester_id,
                'to_user_id' => $request->owner_id,
                'amount' => $amount,
                'type' => 'debit',
                'note' => "Exchange #{$request->id}",
            ]);

            Transaction::create([
                'request_id' => $request->id,
                'from_user_id' => $request->requester_id,
                'to_user_id' => $request->owner_id,
                'amount' => $amount,
                'type' => 'credit',
                'note' => "Exchange #{$request->id}",
            ]);

            DB::table('users')->where('id', $request->requester_id)->decrement('time_bank_balance', $amount);
            DB::table('users')->where('id', $request->owner_id)->increment('time_bank_balance', $amount);
        });
    }

    public function adjust(int $userId, float $amount, string $note = ''): void
    {
        DB::transaction(function () use ($userId, $amount, $note) {
            Transaction::create([
                'to_user_id' => $userId,
                'amount' => abs($amount),
                'type' => 'adjustment',
                'note' => $note,
            ]);

            if ($amount >= 0) {
                DB::table('users')->where('id', $userId)->increment('time_bank_balance', abs($amount));
            } else {
                DB::table('users')->where('id', $userId)->decrement('time_bank_balance', abs($amount));
            }
        });
    }
}
