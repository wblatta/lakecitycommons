<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(private ReferralService $referralService) {}

    public function index(Request $request)
    {
        $tokens = $request->user()->referralTokens()->latest()->get();
        return view('invite.index', compact('tokens'));
    }

    public function store(Request $request)
    {
        $request->validate(['invitee_email' => 'nullable|email|max:255']);

        $token = $this->referralService->generate($request->user(), $request->invitee_email);

        return back()->with('link', route('register', $token->token));
    }
}
