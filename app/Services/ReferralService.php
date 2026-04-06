<?php

namespace App\Services;

use App\Models\ReferralToken;
use App\Models\User;
use Illuminate\Support\Str;

class ReferralService
{
    public function generate(User $inviter, ?string $inviteeEmail = null): ReferralToken
    {
        return ReferralToken::create([
            'token' => Str::random(64),
            'inviter_id' => $inviter->id,
            'invitee_email' => $inviteeEmail,
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function findValid(string $token): ?ReferralToken
    {
        $referral = ReferralToken::where('token', $token)->first();

        if (!$referral || !$referral->isValid()) {
            return null;
        }

        return $referral;
    }

    public function markUsed(ReferralToken $token, User $user): void
    {
        $token->update([
            'used_at' => now(),
            'used_by_user_id' => $user->id,
        ]);
    }
}
