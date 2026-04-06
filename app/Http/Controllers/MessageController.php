<?php

namespace App\Http\Controllers;

use App\Models\Thread;
use App\Services\MessageService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private MessageService $messageService) {}

    public function index(Request $request)
    {
        $threads = Thread::whereHas('participants', fn($q) => $q->where('user_id', $request->user()->id))
            ->with(['latestMessage.sender:id,name', 'participants.user:id,name,avatar'])
            ->latest('updated_at')
            ->paginate(20);

        return view('messages.index', compact('threads'));
    }

    public function show(Request $request, Thread $thread)
    {
        $this->authorize('view', $thread);
        $this->messageService->markRead($thread, $request->user());
        $messages = $thread->messages()->with('sender:id,name,avatar')->get();
        return view('messages.show', compact('thread', 'messages'));
    }

    public function store(Request $request, Thread $thread)
    {
        $this->authorize('view', $thread);
        $request->validate(['body' => 'required|string|max:2000']);
        $this->messageService->send($thread, $request->user(), $request->body);
        return back();
    }

    public function poll(Request $request, Thread $thread)
    {
        $this->authorize('view', $thread);
        $afterId = (int) $request->query('after', 0);
        $messages = $this->messageService->pollNewMessages($thread, $afterId);
        return response()->json($messages);
    }

    public function unreadCount(Request $request)
    {
        return response()->json(['count' => $this->messageService->unreadCount($request->user())]);
    }
}
