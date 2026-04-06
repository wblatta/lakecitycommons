<x-app-layout>
    @section('title', 'Browse Skills')

    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="font-display text-2xl font-semibold text-earth">Skills</h1>
            <a href="{{ route('skills.create') }}" class="px-4 py-2 bg-forest text-white text-sm font-semibold rounded-lg hover:bg-forest-dark transition-colors">
                + Offer a Skill
            </a>
        </div>

        {{-- Category filter chips --}}
        <div class="flex gap-2 overflow-x-auto pb-2 mb-6 no-scrollbar">
            <a href="{{ route('skills.index') }}"
               class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium border transition-colors {{ !request('category') ? 'bg-forest text-white border-forest' : 'bg-white text-earth-muted border-forest-pale hover:border-forest hover:text-forest' }}">
                All
            </a>
            @foreach($categories as $cat)
                <a href="{{ route('skills.index', ['category' => $cat->slug]) }}"
                   class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium border transition-colors {{ request('category') === $cat->slug ? 'bg-forest text-white border-forest' : 'bg-white text-earth-muted border-forest-pale hover:border-forest hover:text-forest' }}">
                    {{ $cat->name }}
                </a>
            @endforeach
        </div>

        @if($skills->isEmpty())
            <div class="text-center py-16 text-earth-muted">
                <p class="text-lg font-medium">No skills listed yet.</p>
                <p class="text-sm mt-1">Be the first to offer a skill to your neighbors.</p>
                <a href="{{ route('skills.create') }}" class="mt-4 inline-block px-6 py-2 bg-forest text-white font-semibold rounded-lg text-sm hover:bg-forest-dark transition-colors">Offer a Skill</a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($skills as $skill)
                    <a href="{{ route('skills.show', $skill) }}"
                       class="bg-white rounded-card p-5 shadow-sm hover:shadow-md border border-transparent hover:border-forest-pale transition-all block">
                        <div class="flex items-start justify-between mb-3">
                            <span class="text-xs font-semibold uppercase tracking-wide text-forest-light bg-forest-pale px-2.5 py-1 rounded-full">
                                {{ $skill->category->name }}
                            </span>
                            <span class="text-xs font-medium text-earth-muted">
                                @if($skill->credit_type === 'gift') Gift
                                @elseif($skill->credit_type === 'time_equal') 1 hr = 1 hr
                                @else {{ number_format($skill->custom_credit_value, 1) }} hrs
                                @endif
                            </span>
                        </div>
                        <h3 class="font-semibold text-earth mb-1">{{ $skill->title }}</h3>
                        <p class="text-earth-muted text-sm line-clamp-2">{{ $skill->description }}</p>
                        <div class="mt-4 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-forest-pale flex items-center justify-center text-forest font-bold text-xs">
                                {{ substr($skill->user->name, 0, 1) }}
                            </div>
                            <div>
                                <p class="text-xs font-medium text-earth">{{ $skill->user->name }}</p>
                                <p class="text-xs text-earth-muted">{{ $skill->user->neighborhood_area }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-8">{{ $skills->links() }}</div>
        @endif
    </div>
</x-app-layout>
