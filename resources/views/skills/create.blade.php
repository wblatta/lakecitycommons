<x-app-layout>
    @section('title', 'Offer a Skill')

    <div class="max-w-2xl mx-auto px-4 py-8">
        <a href="{{ route('skills.index') }}" class="text-forest text-sm hover:underline mb-4 inline-block">&larr; Back to skills</a>
        <h1 class="font-display text-2xl font-semibold text-earth mb-6">Offer a Skill</h1>

        <form method="POST" action="{{ route('skills.store') }}" class="bg-white rounded-card p-6 shadow-sm space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-earth mb-1.5">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" required
                       class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest"
                       placeholder="e.g. Guitar lessons, Home repairs, Dog walking">
                @error('title')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-earth mb-1.5">Description</label>
                <textarea name="description" rows="4" required
                          class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest resize-none"
                          placeholder="Describe what you're offering, your experience, and any requirements.">{{ old('description') }}</textarea>
                @error('description')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-earth mb-1.5">Category</label>
                <select name="category_id" required
                        class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest bg-white">
                    <option value="">Select a category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div x-data="{ creditType: '{{ old('credit_type', 'time_equal') }}' }">
                <label class="block text-sm font-medium text-earth mb-2">Credit Type</label>
                <div class="grid grid-cols-3 gap-3">
                    @foreach(['gift' => 'Gift (free)', 'time_equal' => '1 hr = 1 hr', 'custom' => 'Custom rate'] as $value => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="credit_type" value="{{ $value }}" x-model="creditType" class="sr-only" {{ old('credit_type', 'time_equal') === $value ? 'checked' : '' }}>
                            <div :class="creditType === '{{ $value }}' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                                 class="border-2 rounded-lg p-3 text-center text-sm font-medium transition-colors">
                                {{ $label }}
                            </div>
                        </label>
                    @endforeach
                </div>
                <div x-show="creditType === 'custom'" x-cloak class="mt-3">
                    <label class="block text-sm font-medium text-earth mb-1.5">Hours per session</label>
                    <input type="number" name="custom_credit_value" value="{{ old('custom_credit_value') }}" step="0.25" min="0"
                           class="w-40 px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest"
                           placeholder="1.5">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit"
                        class="w-full bg-forest text-white font-semibold py-3 rounded-lg hover:bg-forest-dark transition-colors focus:outline-none focus:ring-2 focus:ring-forest focus:ring-offset-2">
                    List Skill
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
