<?php

namespace App\Http\Middleware;

use App\Services\ReferralService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReferralToken
{
    public function __construct(private ReferralService $referralService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        if (!$token || !$this->referralService->findValid($token)) {
            abort(403, 'This invitation link is invalid or has expired.');
        }

        return $next($request);
    }
}
