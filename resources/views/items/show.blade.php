<x-app-layout>
    @section('title', $item->title)

    <div class="max-w-3xl mx-auto px-4 py-8">
        <a href="{{ route('items.index') }}" class="text-forest text-sm hover:underline mb-4 inline-block">&larr; Back to items</a>

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
                    <div class="flex gap-2">
                        <span class="text-xs font-semibold text-forest-light bg-forest-pale px-3 py-1 rounded-full">{{ $item->category->name }}</span>
                        <span class="text-xs font-semibold text-earth-muted bg-cream px-3 py-1 rounded-full capitalize">{{ $item->condition }}</span>
                    </div>
                    @if(auth()->id() === $item->user_id)
                        <a href="{{ route('items.edit', $item) }}" class="text-xs text-earth-muted hover:text-forest font-medium">Edit</a>
                    @endif
                </div>

                <h1 class="font-display text-2xl font-semibold text-earth mb-3">{{ $item->title }}</h1>
                <p class="text-earth-muted leading-relaxed">{{ $item->description }}</p>

                <div class="mt-6 flex items-center gap-4 p-4 bg-cream rounded-lg">
                    <div class="w-10 h-10 rounded-full bg-forest-pale flex items-center justify-center text-forest font-bold">
                        {{ substr($item->user->name, 0, 1) }}
                    </div>
                    <div>
                        <p class="font-medium text-earth">{{ $item->user->name }}</p>
                        <p class="text-sm text-earth-muted">{{ $item->user->neighborhood_area }}</p>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-earth-muted font-medium uppercase tracking-wide mb-1">Credit</p>
                        <p class="font-semibold text-earth">
                            @if($item->credit_type === 'gift') Free (gift)
                            @elseif($item->credit_type === 'time_equal') 1 hour = 1 hour
                            @else {{ number_format($item->custom_credit_value, 1) }} hrs
                            @endif
                        </p>
                    </div>

                    @if(auth()->id() !== $item->user_id && $item->is_available)
                        <a href="{{ route('requests.create', ['type' => 'item', 'id' => $item->id]) }}"
                           class="px-6 py-2.5 bg-forest text-white font-semibold rounded-lg hover:bg-forest-dark transition-colors text-sm">
                            Request this Item
                        </a>
                    @elseif(!$item->is_available)
                        <span class="text-sm text-earth-muted font-medium px-4 py-2 bg-gray-100 rounded-lg">Currently unavailable</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
