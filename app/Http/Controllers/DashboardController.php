<?php

namespace App\Http\Controllers;

use App\Services\MessageService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, MessageService $messageService)
    {
        $user = $request->user()->load(['skills', 'items']);

        $recentRequests = $user->sentRequests()
            ->with(['owner:id,name', 'thread'])
            ->latest()
            ->take(5)
            ->get();

        $pendingForMe = $user->receivedRequests()
            ->where('status', 'pending')
            ->with(['requester:id,name'])
            ->latest()
            ->take(5)
            ->get();

        $unreadCount = $messageService->unreadCount($user);

        return view('dashboard', compact('user', 'recentRequests', 'pendingForMe', 'unreadCount'));
    }
}
