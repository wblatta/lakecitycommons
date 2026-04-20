<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
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

        $before = $user->status;
        $user->update(['status' => $request->status]);

        AdminAuditLog::create([
            'admin_id'       => $request->user()->id,
            'target_user_id' => $user->id,
            'action'         => 'status_change',
            'payload'        => ['before' => $before, 'after' => $request->status],
            'ip_address'     => $request->ip(),
        ]);

        return back()->with('success', "Member status updated to {$request->status}.");
    }

    public function adjustCredits(Request $request, User $user, CreditService $creditService)
    {
        $request->validate([
            'amount' => 'required|numeric|min:-100|max:100',
            'note'   => 'required|string|max:255',
        ]);

        $balanceBefore = (float) $user->time_bank_balance;
        $creditService->adjust($user->id, (float) $request->amount, $request->note);
        $user->refresh();

        AdminAuditLog::create([
            'admin_id'       => $request->user()->id,
            'target_user_id' => $user->id,
            'action'         => 'credit_adjustment',
            'payload'        => [
                'amount'         => (float) $request->amount,
                'note'           => $request->note,
                'balance_before' => $balanceBefore,
                'balance_after'  => (float) $user->time_bank_balance,
            ],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Credit adjustment applied.');
    }
}
