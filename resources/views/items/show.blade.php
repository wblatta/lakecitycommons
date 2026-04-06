<x-app-layout>
    @section('title', $item->title)

    <div class="max-w-3xl mx-auto px-4 py-8">
        <a href="{{ route('items.index') }}" class="text-forest text-sm hover:underline mb-4 inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to items
        </a>

        <div class="bg-white rounded-card shadow-sm overflow-hidden">
            @php $photos = $item->getMedia('photos'); @endphp
            @if($photos->isNotEmpty())
                <div class="grid {{ $photos->count() > 1 ? 'grid-cols-2' : 'grid-cols-1' }} gap-1">
                    @foreach($photos->take(4) as $photo)
                        <img src="{{ $photo->getUrl() }}" alt="{{ $item->title }}" class="w-full h-56 object-cover">
                    @endforeach
                </div>
            @endif

            <div class="p-6 md:p-8">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex gap-2 flex-wrap">
                        <span class="text-xs font-semibold text-forest-light bg-forest-pale px-3 py-1 rounded-full">{{ $item->category->name }}</span>
                        <span class="text-xs font-semibold text-earth-muted bg-cream px-3 py-1 rounded-full capitalize">{{ $item->condition }}</span>
                        @if(!$item->is_available)
                            <span class="badge-hold">On Hold</span>
                        @endif
                    </div>
                    @if(auth()->id() === $item->user_id)
                        <div class="flex gap-3">
                            <form method="POST" action="{{ route('items.toggle', $item) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-xs font-medium text-earth-muted hover:text-forest transition-colors">
                                    {{ $item->is_available ? 'Place on Hold' : 'Make Available' }}
                                </button>
                            </form>
                            <a href="{{ route('items.edit', $item) }}" class="text-xs text-earth-muted hover:text-forest font-medium">Edit</a>
                        </div>
                    @endif
                </div>

                <h1 class="font-display text-2xl font-semibold text-earth mb-3">{{ $item->title }}</h1>
                <p class="text-earth-muted leading-relaxed">{{ $item->description }}</p>

                <a href="{{ route('users.show', $item->user) }}"
                   class="mt-6 flex items-center gap-4 p-4 bg-cream rounded-lg hover:bg-forest-pale/30 transition-colors group">
                    @if($item->user->avatar)
                        <img src="{{ $item->user->avatarUrl() }}" alt="{{ $item->user->name }}"
                             class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                    @else
                        <div class="w-10 h-10 rounded-full bg-forest-pale flex items-center justify-center text-forest font-bold flex-shrink-0">
                            {{ $item->user->initials() }}
                        </div>
                    @endif
                    <div>
                        <p class="font-medium text-earth group-hover:text-forest transition-colors">{{ $item->user->name }}</p>
                        <p class="text-sm text-earth-muted">{{ $item->user->neighborhood_area }}</p>
                    </div>
                </a>

                <div class="mt-6 flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs text-earth-muted font-medium uppercase tracking-wide mb-1">Credit</p>
                        <p class="font-semibold text-earth">
                            @if($item->credit_type === 'gift') Free (gift)
                            @elseif($item->credit_type === 'time_equal') 1 hour = 1 hour
                            @else {{ number_format($item->custom_credit_value, 1) }} hrs
                            @endif
                        </p>
                    </div>

                    @if(auth()->id() !== $item->user_id)
                        @if($item->is_available)
                            <a href="{{ route('requests.create', ['type' => 'item', 'id' => $item->id]) }}"
                               class="btn-primary px-6 py-2.5 text-sm">
                                Request this Item
                            </a>
                        @else
                            <div class="flex flex-col items-end gap-2">
                                <span class="text-sm text-earth-muted font-medium px-4 py-2 bg-gray-100 rounded-lg">Currently on hold</span>
                                @if($onWaitlist)
                                    <form method="POST" action="{{ route('waitlist.destroy', auth()->user()->waitlistEntries()->where('resource_type', 'item')->where('resource_id', $item->id)->first()) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-earth-muted hover:text-red-500 font-medium transition-colors">
                                            Leave waitlist
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('waitlist.store') }}">
                                        @csrf
                                        <input type="hidden" name="resource_type" value="item">
                                        <input type="hidden" name="resource_id" value="{{ $item->id }}">
                                        <button type="submit" class="btn-outline px-4 py-2 text-sm">
                                            Join Waitlist
                                            @if($waitlistCount > 0)
                                                <span class="ml-1 text-xs opacity-70">({{ $waitlistCount }} waiting)</span>
                                            @endif
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
