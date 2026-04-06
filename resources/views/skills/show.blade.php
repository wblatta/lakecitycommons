<x-app-layout>
    @section('title', $skill->title)

    <div class="max-w-3xl mx-auto px-4 py-8">
        <a href="{{ route('skills.index') }}" class="text-forest text-sm hover:underline mb-4 inline-block">&larr; Back to skills</a>

        <div class="bg-white rounded-card shadow-sm overflow-hidden">
            <div class="p-6 md:p-8">
                <div class="flex items-start justify-between mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wide text-forest-light bg-forest-pale px-3 py-1 rounded-full">
                        {{ $skill->category->name }}
                    </span>
                    @if(auth()->id() === $skill->user_id)
                        <div class="flex gap-2">
                            <a href="{{ route('skills.edit', $skill) }}" class="text-xs text-earth-muted hover:text-forest font-medium">Edit</a>
                        </div>
                    @endif
                </div>

                <h1 class="font-display text-2xl font-semibold text-earth mb-3">{{ $skill->title }}</h1>
                <p class="text-earth-muted leading-relaxed">{{ $skill->description }}</p>

                <div class="mt-6 flex items-center gap-4 p-4 bg-cream rounded-lg">
                    <div class="w-10 h-10 rounded-full bg-forest-pale flex items-center justify-center text-forest font-bold">
                        {{ substr($skill->user->name, 0, 1) }}
                    </div>
                    <div>
                        <p class="font-medium text-earth">{{ $skill->user->name }}</p>
                        <p class="text-sm text-earth-muted">{{ $skill->user->neighborhood_area }}</p>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-earth-muted font-medium uppercase tracking-wide mb-1">Credit</p>
                        <p class="font-semibold text-earth">
                            @if($skill->credit_type === 'gift') Free (gift)
                            @elseif($skill->credit_type === 'time_equal') 1 hour = 1 hour
                            @else {{ number_format($skill->custom_credit_value, 1) }} hrs per session
                            @endif
                        </p>
                    </div>

                    @if(auth()->id() !== $skill->user_id && $skill->is_available)
                        <a href="{{ route('requests.create', ['type' => 'skill', 'id' => $skill->id]) }}"
                           class="px-6 py-2.5 bg-forest text-white font-semibold rounded-lg hover:bg-forest-dark transition-colors text-sm">
                            Request this Skill
                        </a>
                    @elseif(!$skill->is_available)
                        <span class="text-sm text-earth-muted font-medium px-4 py-2 bg-gray-100 rounded-lg">Currently unavailable</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
