<x-app-layout>
    @section('title', $thread->subject ?? 'Message')

    <div class="max-w-2xl mx-auto px-4 py-8 flex flex-col" style="height: calc(100vh - 80px)">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('messages.index') }}" class="text-forest hover:underline text-sm">&larr;</a>
            <h1 class="font-display text-lg font-semibold text-earth">{{ $thread->subject }}</h1>
        </div>

        {{-- Messages --}}
        <div id="messages" class="flex-1 overflow-y-auto space-y-3 pb-4"
             x-data="chatPoll({{ $messages->last()?->id ?? 0 }}, {{ $thread->id }})"
             x-init="init()">
            @foreach($messages as $message)
                @php $isMine = $message->sender_id === auth()->id(); @endphp
                <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[75%]">
                        @if(!$isMine)
                            <p class="text-xs text-earth-muted mb-1 ml-1">{{ $message->sender->name }}</p>
                        @endif
                        <div class="{{ $isMine ? 'bg-forest text-white' : 'bg-white text-earth border border-forest-pale' }} rounded-2xl px-4 py-2.5 text-sm leading-relaxed">
                            {{ $message->body }}
                        </div>
                        <p class="text-xs text-earth-muted mt-1 {{ $isMine ? 'text-right mr-1' : 'ml-1' }}">
                            {{ $message->created_at->format('g:ia') }}
                        </p>
                    </div>
                </div>
            @endforeach

            {{-- Polled new messages appended here --}}
            <template x-for="msg in newMessages" :key="msg.id">
                <div :class="msg.sender_id === {{ auth()->id() }} ? 'flex justify-end' : 'flex justify-start'">
                    <div class="max-w-[75%]">
                        <div :class="msg.sender_id === {{ auth()->id() }} ? 'bg-forest text-white' : 'bg-white text-earth border border-forest-pale'"
                             class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed"
                             x-text="msg.body"></div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Send form --}}
        <form method="POST" action="{{ route('messages.store', $thread) }}" class="flex gap-3 mt-2">
            @csrf
            <input type="text" name="body" required autocomplete="off"
                   class="flex-1 px-4 py-3 rounded-xl border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest text-sm"
                   placeholder="Type a message...">
            <button type="submit"
                    class="px-5 py-3 bg-forest text-white font-semibold rounded-xl hover:bg-forest-dark transition-colors text-sm">
                Send
            </button>
        </form>
    </div>

    <script>
        function chatPoll(lastId, threadId) {
            return {
                lastId,
                newMessages: [],
                init() {
                    setInterval(() => this.poll(), 5000);
                },
                poll() {
                    fetch(`/messages/${threadId}/poll?after=${this.lastId}`)
                        .then(r => r.json())
                        .then(msgs => {
                            if (msgs.length) {
                                this.newMessages.push(...msgs);
                                this.lastId = msgs[msgs.length - 1].id;
                            }
                        });
                }
            };
        }
    </script>
</x-app-layout>
