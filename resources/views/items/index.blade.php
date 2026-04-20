<x-app-layout>
    @section('title', 'Browse Items')

    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="font-display text-2xl font-semibold text-earth">Items</h1>
            <a href="{{ route('items.create') }}" class="px-4 py-2 bg-forest text-white text-sm font-semibold rounded-lg hover:bg-forest-dark transition-colors">
                + List an Item
            </a>
        </div>

        <div class="flex gap-2 overflow-x-auto pb-2 mb-6">
            <a href="{{ route('items.index') }}"
               class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium border transition-colors {{ !request('category') ? 'bg-forest text-white border-forest' : 'bg-white text-earth-muted border-forest-pale hover:border-forest hover:text-forest' }}">
                All
            </a>
            @foreach($categories as $cat)
                <a href="{{ route('items.index', ['category' => $cat->slug]) }}"
                   class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium border transition-colors {{ request('category') === $cat->slug ? 'bg-forest text-white border-forest' : 'bg-white text-earth-muted border-forest-pale hover:border-forest hover:text-forest' }}">
                    {{ $cat->name }}
                </a>
            @endforeach
        </div>

        @if($items->isEmpty())
            <div class="text-center py-16 text-earth-muted">
                <p class="text-lg font-medium">No items listed yet.</p>
                <a href="{{ route('items.create') }}" class="mt-4 inline-block px-6 py-2 bg-forest text-white font-semibold rounded-lg text-sm hover:bg-forest-dark transition-colors">List an Item</a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($items as $item)
                    <a href="{{ route('items.show', $item) }}"
                       class="bg-white rounded-card shadow-sm hover:shadow-md border border-transparent hover:border-forest-pale transition-all block overflow-hidden">
                        @php $photo = $item->getFirstMedia('photos'); @endphp
                        @if($photo)
                            <img src="{{ $photo->getUrl() }}" alt="{{ $item->title }}" class="w-full h-40 object-cover">
                        @else
                            <div class="w-full h-40 bg-forest-pale flex items-center justify-center">
                                <svg class="w-10 h-10 text-forest opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                        @endif
                        <div class="p-5">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-forest-light bg-forest-pale px-2.5 py-1 rounded-full">{{ $item->category->name }}</span>
                                <span class="text-xs text-earth-muted capitalize">{{ $item->condition }}</span>
                            </div>
                            <h3 class="font-semibold text-earth mb-1">{{ $item->title }}</h3>
                            <p class="text-earth-muted text-sm line-clamp-2">{{ $item->description }}</p>
                            <div class="mt-4 flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-forest-pale flex items-center justify-center text-forest font-bold text-xs">
                                    {{ substr($item->user->name, 0, 1) }}
                                </div>
                                <p class="text-xs text-earth-muted">{{ $item->user->name }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-8">{{ $items->links() }}</div>
        @endif

        @if($archivedItems->isNotEmpty())
            <div class="mt-12">
                <h2 class="font-display text-lg font-semibold text-earth-muted mb-4">Your Archived Items</h2>
                <div class="space-y-3">
                    @foreach($archivedItems as $item)
                        <div class="bg-white rounded-card shadow-sm border border-gray-100 p-4 flex items-center justify-between gap-4 opacity-60">
                            <div>
                                <p class="font-medium text-earth text-sm">{{ $item->title }}</p>
                                <p class="text-xs text-earth-muted mt-0.5">{{ $item->category->name }} &middot; Gifted {{ $item->updated_at->diffForHumans() }}</p>
                            </div>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">Archived</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
