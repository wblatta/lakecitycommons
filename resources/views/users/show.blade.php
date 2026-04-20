<x-app-layout>
    @section('title', $user->name)

    {{-- Profile hero --}}
    <div class="bg-gradient-to-br from-forest-dark via-forest to-forest-light text-white">
        <div class="max-w-4xl mx-auto px-4 py-10 flex flex-col sm:flex-row items-center sm:items-end gap-5">
            @if($user->avatar)
                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}"
                     class="w-24 h-24 rounded-full object-cover ring-4 ring-white/30 flex-shrink-0">
            @else
                <div class="w-24 h-24 rounded-full bg-white/20 flex items-center justify-center text-white font-bold text-3xl flex-shrink-0 ring-4 ring-white/30">
                    {{ $user->initials() }}
                </div>
            @endif
            <div class="text-center sm:text-left flex-1">
                <h1 class="font-display text-3xl font-semibold">{{ $user->name }}</h1>
                @if($user->neighborhood_area)
                    <p class="text-forest-pale mt-0.5 text-sm">
                        {{ $user->neighborhood_area }}
                        @if($canSeeCrossStreets && $user->cross_streets)
                            &middot; near {{ $user->cross_streets }}
                        @endif
                    </p>
                @endif
                <p class="text-forest-pale text-xs mt-1">Member since {{ $user->created_at->format('F Y') }}</p>
            </div>
            <div class="flex flex-col items-center gap-2">
                <span class="bg-white/20 text-white text-sm font-semibold rounded-full px-4 py-1.5 tabular-nums">
                    {{ number_format($user->time_bank_balance, 1) }} hrs
                </span>
                @if($canMessage)
                    @php
                        // Find existing thread between auth user and this user
                        $existingThread = \App\Models\Thread::whereHas('participants', fn($q) => $q->where('user_id', auth()->id()))
                            ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
                            ->first();
                    @endphp
                    @if($existingThread)
                        <a href="{{ route('messages.show', $existingThread) }}"
                           class="px-4 py-2 bg-white text-forest font-semibold text-sm rounded-lg hover:bg-forest-pale transition-colors">
                            Message
                        </a>
                    @else
                        <a href="{{ route('messages.index', ['to' => $user->id]) }}"
                           class="px-4 py-2 bg-white text-forest font-semibold text-sm rounded-lg hover:bg-forest-pale transition-colors">
                            Message
                        </a>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">

        {{-- Bio --}}
        @if($user->bio)
            <div class="bg-white rounded-card shadow-sm p-6">
                <h2 class="font-display text-lg font-semibold text-earth mb-3">About</h2>
                <p class="text-earth-muted leading-relaxed">{{ $user->bio }}</p>
            </div>
        @endif

        {{-- Skills --}}
        @if($user->skills->isNotEmpty())
            <div>
                <h2 class="font-display text-xl font-semibold text-earth mb-4">Skills Offered</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($user->skills as $skill)
                        <a href="{{ route('skills.show', $skill) }}"
                           class="card card-hover p-5 block group">
                            <div class="flex items-start justify-between mb-2">
                                <span class="text-xs font-semibold text-forest bg-forest-pale px-2.5 py-0.5 rounded-full">
                                    {{ $skill->category->name }}
                                </span>
                                @if($skill->credit_type === 'gift')
                                    <span class="badge-gift">Free</span>
                                @elseif($skill->credit_type === 'time_equal')
                                    <span class="badge-credit">Time equal</span>
                                @else
                                    <span class="badge-credit">{{ number_format($skill->custom_credit_value, 1) }} hrs</span>
                                @endif
                            </div>
                            <h3 class="font-semibold text-earth group-hover:text-forest transition-colors">{{ $skill->title }}</h3>
                            <p class="text-sm text-earth-muted mt-1 line-clamp-2">{{ $skill->description }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Items --}}
        @if($user->items->isNotEmpty())
            <div>
                <h2 class="font-display text-xl font-semibold text-earth mb-4">Items to Share</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($user->items as $item)
                        <a href="{{ route('items.show', $item) }}"
                           class="card card-hover p-5 block group">
                            <div class="flex items-start justify-between mb-2">
                                <span class="text-xs font-semibold text-forest bg-forest-pale px-2.5 py-0.5 rounded-full">
                                    {{ $item->category->name }}
                                </span>
                                <span class="text-xs text-earth-muted font-medium capitalize">{{ $item->condition }}</span>
                            </div>
                            <h3 class="font-semibold text-earth group-hover:text-forest transition-colors">{{ $item->title }}</h3>
                            <p class="text-sm text-earth-muted mt-1 line-clamp-2">{{ $item->description }}</p>
                            <div class="mt-2">
                                @if($item->credit_type === 'gift')
                                    <span class="badge-gift">Free</span>
                                @elseif($item->credit_type === 'time_equal')
                                    <span class="badge-credit">Time equal</span>
                                @else
                                    <span class="badge-credit">{{ number_format($item->custom_credit_value, 1) }} hrs</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if($user->skills->isEmpty() && $user->items->isEmpty())
            <div class="text-center py-12 text-earth-muted">
                <p>{{ $user->name }} hasn't listed any skills or items yet.</p>
            </div>
        @endif
    </div>
</x-app-layout>
