<x-app-layout>
    @section('title', 'Exchange Request')

    <div class="max-w-3xl mx-auto px-4 py-8">
        <a href="{{ route('requests.index') }}" class="text-forest text-sm hover:underline mb-4 inline-block">&larr; Back to requests</a>

        @php
            $req = $exchangeRequest;
            $isOwner = auth()->id() === $req->owner_id;
            $isRequester = auth()->id() === $req->requester_id;
            $statusColors = ['pending'=>'bg-amber text-white','accepted'=>'bg-forest-light text-white','in_progress'=>'bg-forest text-white','completed'=>'bg-earth text-white','returned'=>'bg-gray-200 text-gray-600','declined'=>'bg-red-100 text-red-700','cancelled'=>'bg-gray-100 text-gray-500'];
        @endphp

        <div class="bg-white rounded-card shadow-sm p-6 mb-5">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h1 class="font-display text-xl font-semibold text-earth">Exchange #{{ $req->id }}</h1>
                    <p class="text-earth-muted text-sm mt-1">{{ ucfirst($req->resource_type) }} &middot; {{ $req->proposed_datetime->format('l, M j Y g:ia') }}</p>
                </div>
                <span class="text-xs font-semibold px-3 py-1.5 rounded-full {{ $statusColors[$req->status] ?? '' }}">
                    {{ ucfirst(str_replace('_', ' ', $req->status)) }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-4 py-4 border-y border-cream text-sm mb-4">
                <div>
                    <p class="text-earth-muted text-xs uppercase tracking-wide font-medium mb-1">From</p>
                    <p class="font-medium text-earth">{{ $req->requester->name }}</p>
                </div>
                <div>
                    <p class="text-earth-muted text-xs uppercase tracking-wide font-medium mb-1">To</p>
                    <p class="font-medium text-earth">{{ $req->owner->name }}</p>
                </div>
                <div>
                    <p class="text-earth-muted text-xs uppercase tracking-wide font-medium mb-1">Credits</p>
                    <p class="font-medium text-earth">
                        @if($req->credit_type === 'gift') Gift (free)
                        @else {{ number_format($req->credit_value, 1) }} hrs
                        @endif
                    </p>
                </div>
                @if($req->duration_hours)
                    <div>
                        <p class="text-earth-muted text-xs uppercase tracking-wide font-medium mb-1">Duration</p>
                        <p class="font-medium text-earth">{{ number_format($req->duration_hours, 1) }} hrs</p>
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap gap-3">
                @if($isOwner && $req->status === 'pending')
                    <form method="POST" action="{{ route('requests.transition', $req) }}" class="inline">
                        @csrf <input type="hidden" name="status" value="accepted">
                        <button class="px-4 py-2 bg-forest text-white text-sm font-semibold rounded-lg hover:bg-forest-dark transition-colors">Accept</button>
                    </form>
                    <form method="POST" action="{{ route('requests.transition', $req) }}" class="inline">
                        @csrf <input type="hidden" name="status" value="declined">
                        <button class="px-4 py-2 bg-red-50 text-red-600 text-sm font-semibold rounded-lg hover:bg-red-100 transition-colors">Decline</button>
                    </form>
                @endif

                @if(in_array($req->status, ['accepted', 'in_progress']))
                    @if(($isRequester && !$req->requester_confirmed_at) || ($isOwner && !$req->owner_confirmed_at))
                        <form method="POST" action="{{ route('requests.confirm', $req) }}" class="inline">
                            @csrf
                            <button class="px-4 py-2 bg-forest text-white text-sm font-semibold rounded-lg hover:bg-forest-dark transition-colors">Confirm Completion</button>
                        </form>
                    @else
                        <span class="px-4 py-2 bg-forest-pale text-forest text-sm font-medium rounded-lg">Waiting for other party to confirm</span>
                    @endif
                @endif

                @if(in_array($req->status, ['pending', 'accepted']))
                    <form method="POST" action="{{ route('requests.transition', $req) }}" class="inline">
                        @csrf <input type="hidden" name="status" value="cancelled">
                        <button class="px-4 py-2 text-earth-muted text-sm font-medium hover:text-earth transition-colors">Cancel</button>
                    </form>
                @endif

                @if($isOwner && $req->status === 'completed' && $req->resource_type === 'item')
                    @php $lendItem = \App\Models\Item::find($req->resource_id); @endphp
                    @if($lendItem && $lendItem->offer_type === 'lend')
                        <form method="POST" action="{{ route('requests.transition', $req) }}" class="inline">
                            @csrf <input type="hidden" name="status" value="returned">
                            <button class="px-4 py-2 bg-forest-pale text-forest text-sm font-semibold rounded-lg hover:bg-forest hover:text-white transition-colors">
                                Mark as Returned
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        {{-- Thread --}}
        @if($req->thread)
            <div class="bg-white rounded-card shadow-sm p-6">
                <h2 class="font-display text-lg font-semibold text-earth mb-4">Messages</h2>
                <div class="space-y-3 mb-4 max-h-80 overflow-y-auto">
                    @foreach($req->thread->messages as $message)
                        @php $isMine = $message->sender_id === auth()->id(); @endphp
                        <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                            <div class="{{ $isMine ? 'bg-forest text-white' : 'bg-cream text-earth' }} rounded-2xl px-4 py-2.5 text-sm max-w-[80%]">
                                {{ $message->body }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('messages.store', $req->thread) }}" class="flex gap-2">
                    @csrf
                    <input type="text" name="body" required placeholder="Add a message..."
                           class="flex-1 px-4 py-2.5 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest text-sm">
                    <button type="submit" class="px-4 py-2.5 bg-forest text-white text-sm font-semibold rounded-lg hover:bg-forest-dark transition-colors">Send</button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
