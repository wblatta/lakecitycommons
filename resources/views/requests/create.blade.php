<x-app-layout>
    @section('title', 'Make a Request')

    <div class="max-w-2xl mx-auto px-4 py-8">
        <h1 class="font-display text-2xl font-semibold text-earth mb-2">Request {{ ucfirst($resourceType) }}</h1>
        <p class="text-earth-muted text-sm mb-6">You're requesting: <strong class="text-earth">{{ $resource->title }}</strong></p>

        <form method="POST" action="{{ route('requests.store') }}" class="bg-white rounded-card p-6 shadow-sm space-y-5">
            @csrf
            <input type="hidden" name="resource_type" value="{{ $resourceType }}">
            <input type="hidden" name="resource_id" value="{{ $resource->id }}">

            <div>
                <label class="block text-sm font-medium text-earth mb-1.5">Proposed date &amp; time</label>
                <input type="datetime-local" name="proposed_datetime" required
                       class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest">
                @error('proposed_datetime')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            @if($resource->credit_type === 'time_equal')
                <div>
                    <label class="block text-sm font-medium text-earth mb-1.5">Duration (hours)</label>
                    <input type="number" name="duration_hours" step="0.25" min="0.25" max="8" value="{{ old('duration_hours', 1) }}"
                           class="w-40 px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest">
                    @error('duration_hours')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-earth mb-1.5">Message <span class="text-earth-muted font-normal">(optional)</span></label>
                <textarea name="message" rows="3"
                          class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest resize-none"
                          placeholder="Introduce yourself or add any details...">{{ old('message') }}</textarea>
            </div>

            <div class="p-4 bg-cream rounded-lg text-sm">
                <p class="font-medium text-earth mb-1">Credit estimate</p>
                <p class="text-earth-muted">
                    @if($resource->credit_type === 'gift') This is a gift — no credits will be exchanged.
                    @elseif($resource->credit_type === 'time_equal') Credits depend on duration (1 hr = 1 hr).
                    @else {{ number_format($resource->custom_credit_value, 1) }} hrs will be deducted from your balance.
                    @endif
                </p>
            </div>

            <button type="submit"
                    class="w-full bg-forest text-white font-semibold py-3 rounded-lg hover:bg-forest-dark transition-colors">
                Send Request
            </button>
        </form>
    </div>
</x-app-layout>
