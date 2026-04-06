<x-app-layout>
    @section('title', 'Messages')

    <div class="max-w-2xl mx-auto px-4 py-8">
        <h1 class="font-display text-2xl font-semibold text-earth mb-6">Messages</h1>

        @if($threads->isEmpty())
            <div class="text-center py-16 text-earth-muted">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <p>No messages yet. Messages appear here when you make or receive a request.</p>
            </div>
        @else
            <div class="bg-white rounded-card shadow-sm divide-y divide-cream">
                @foreach($threads as $thread)
                    @php
                        $other = $thread->participants->firstWhere('user_id', '!=', auth()->id());
                        $latest = $thread->latestMessage;
                    @endphp
                    <a href="{{ route('messages.show', $thread) }}"
                       class="flex items-center gap-4 px-5 py-4 hover:bg-cream transition-colors">
                        <div class="w-10 h-10 rounded-full bg-forest-pale flex items-center justify-center text-forest font-bold flex-shrink-0">
                            {{ substr($other?->user?->name ?? '?', 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-0.5">
                                <p class="font-medium text-earth text-sm">{{ $other?->user?->name ?? 'Unknown' }}</p>
                                @if($latest)
                                    <p class="text-xs text-earth-muted flex-shrink-0">{{ $latest->created_at->diffForHumans(short: true) }}</p>
                                @endif
                            </div>
                            <p class="text-xs text-earth-muted truncate">{{ $thread->subject }}</p>
                            @if($latest)
                                <p class="text-xs text-earth-muted truncate mt-0.5">{{ $latest->body }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $threads->links() }}</div>
        @endif
    </div>
</x-app-layout>
