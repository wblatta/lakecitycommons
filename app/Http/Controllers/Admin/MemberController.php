<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $members = User::with('referrer:id,name')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('admin.members.index', compact('members'));
    }

    public function updateStatus(Request $request, User $user)
    {
        $request->validate(['status' => 'required|in:active,suspended,pending']);
        $user->update(['status' => $request->status]);
        return back()->with('success', "Member status updated to {$request->status}.");
    }

    public function adjustCredits(Request $request, User $user, CreditService $creditService)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'note' => 'required|string|max:255',
        ]);

        $creditService->adjust($user->id, (float) $request->amount, $request->note);

        return back()->with('success', 'Credit adjustment applied.');
    }
}
