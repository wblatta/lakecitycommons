<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\Post;
use App\Models\Skill;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        // Announcements: latest 3 published posts
        $announcements = Post::published()
            ->with('user:id,name')
            ->latest('published_at')
            ->take(3)
            ->get();

        // Activity feed: last 21 days, mixed sources, 15 items
        $since = Carbon::now()->subDays(21);

        $newSkills = Skill::with('user:id,name,avatar')
            ->where('created_at', '>=', $since)
            ->where('is_available', true)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($s) => [
                'type'       => 'skill',
                'label'      => "{$s->user->name} offered a new skill",
                'detail'     => $s->title,
                'url'        => route('skills.show', $s),
                'user'       => $s->user,
                'created_at' => $s->created_at,
            ]);

        $newItems = Item::with('user:id,name,avatar')
            ->where('created_at', '>=', $since)
            ->where('is_available', true)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($i) => [
                'type'       => 'item',
                'label'      => "{$i->user->name} listed a new item",
                'detail'     => $i->title,
                'url'        => route('items.show', $i),
                'user'       => $i->user,
                'created_at' => $i->created_at,
            ]);

        $completedExchanges = ExchangeRequest::with(['requester:id,name,avatar', 'owner:id,name'])
            ->where('status', 'completed')
            ->where('completed_at', '>=', $since)
            ->latest('completed_at')
            ->take(10)
            ->get()
            ->map(fn($r) => [
                'type'       => 'exchange',
                'label'      => "{$r->requester->name} and {$r->owner->name} completed an exchange",
                'detail'     => ucfirst($r->resource_type) . ' · ' . number_format($r->credit_value, 1) . ' hrs',
                'url'        => null,
                'user'       => $r->requester,
                'created_at' => $r->completed_at,
            ]);

        $newMembers = User::where('created_at', '>=', $since)
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($m) => [
                'type'       => 'member',
                'label'      => "{$m->name} joined the community",
                'detail'     => $m->neighborhood_area,
                'url'        => route('users.show', $m),
                'user'       => $m,
                'created_at' => $m->created_at,
            ]);

        $activity = $newSkills
            ->concat($newItems)
            ->concat($completedExchanges)
            ->concat($newMembers)
            ->sortByDesc('created_at')
            ->take(15)
            ->values();

        return view('dashboard', compact(
            'user', 'recentRequests', 'pendingForMe', 'unreadCount',
            'announcements', 'activity'
        ));
    }
}
