<?php

namespace App\Services;

use App\Models\ExchangeRequest;
use App\Models\Message;
use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\User;

class MessageService
{
    public function createThreadForRequest(ExchangeRequest $request, string $initialMessage): Thread
    {
        $thread = Thread::create([
            'request_id' => $request->id,
            'subject' => "Exchange #{$request->id}",
        ]);

        ThreadParticipant::create(['thread_id' => $thread->id, 'user_id' => $request->requester_id]);
        ThreadParticipant::create(['thread_id' => $thread->id, 'user_id' => $request->owner_id]);

        if ($initialMessage) {
            Message::create([
                'thread_id' => $thread->id,
                'sender_id' => $request->requester_id,
                'body' => $initialMessage,
            ]);
        }

        return $thread;
    }

    public function send(Thread $thread, User $sender, string $body): Message
    {
        $participant = ThreadParticipant::where('thread_id', $thread->id)
            ->where('user_id', $sender->id)
            ->first();

        if (!$participant) {
            throw new \RuntimeException('User is not a participant in this thread.');
        }

        $message = Message::create([
            'thread_id' => $thread->id,
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        $participant->update(['last_read_at' => now()]);

        return $message;
    }

    public function pollNewMessages(Thread $thread, int $afterId): \Illuminate\Database\Eloquent\Collection
    {
        return $thread->messages()->with('sender:id,name,avatar')->where('id', '>', $afterId)->get();
    }

    public function unreadCount(User $user): int
    {
        return ThreadParticipant::where('user_id', $user->id)
            ->whereHas('thread.messages', function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)
                  ->whereColumn('messages.created_at', '>', 'thread_participants.last_read_at');
            })
            ->count();
    }

    public function markRead(Thread $thread, User $user): void
    {
        ThreadParticipant::where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }
}
