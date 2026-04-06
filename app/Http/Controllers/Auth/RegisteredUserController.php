<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private ReferralService $referralService) {}

    public function create(string $token): View
    {
        $referral = $this->referralService->findValid($token);
        return view('auth.register', compact('token', 'referral'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $referral = $this->referralService->findValid($token);

        if (!$referral) {
            abort(403, 'This invitation link is invalid or has expired.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
            'neighborhood_area' => ['nullable', 'string', 'max:100'],
        ]);

        $user = DB::transaction(function () use ($request, $referral) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'neighborhood_area' => $request->neighborhood_area,
                'referred_by' => $referral->inviter_id,
                'status' => 'pending',
            ]);

            $this->referralService->markUsed($referral, $user);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
