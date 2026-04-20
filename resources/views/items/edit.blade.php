<x-app-layout>
    @section('title', 'Edit Item')

    <div class="max-w-2xl mx-auto px-4 py-8">
        <a href="{{ route('items.show', $item) }}" class="text-forest text-sm hover:underline mb-4 inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to item
        </a>
        <h1 class="font-display text-2xl font-semibold text-earth mb-6">Edit Item</h1>

        <form method="POST" action="{{ route('items.update', $item) }}" class="bg-white rounded-card p-6 shadow-sm space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="field-label">Title</label>
                <input type="text" name="title" value="{{ old('title', $item->title) }}" required class="field"
                       placeholder="e.g. Ladder, Stand mixer, Camping tent">
                @error('title')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Description</label>
                <textarea name="description" rows="3" required class="field resize-none">{{ old('description', $item->description) }}</textarea>
                @error('description')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Category</label>
                    <select name="category_id" required class="field bg-white">
                        <option value="">Select</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id', $item->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Condition</label>
                    <select name="condition" required class="field bg-white">
                        @foreach(['excellent', 'good', 'fair', 'poor'] as $c)
                            <option value="{{ $c }}" {{ old('condition', $item->condition) === $c ? 'selected' : '' }}>{{ ucfirst($c) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div x-data="{ offerType: '{{ old('offer_type', $item->offer_type) }}', creditType: '{{ old('credit_type', $item->credit_type) }}' }">
                <label class="block text-sm font-medium text-earth mb-2">How are you offering this?</label>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <label class="cursor-pointer">
                        <input type="radio" name="offer_type" value="lend" x-model="offerType" class="sr-only">
                        <div :class="offerType === 'lend' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                             class="border-2 rounded-lg p-4 transition-colors">
                            <p class="font-semibold text-sm">Lend</p>
                            <p class="text-xs mt-0.5 opacity-70">I want it back</p>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="offer_type" value="gift" x-model="offerType" class="sr-only">
                        <div :class="offerType === 'gift' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                             class="border-2 rounded-lg p-4 transition-colors">
                            <p class="font-semibold text-sm">Gift</p>
                            <p class="text-xs mt-0.5 opacity-70">Keep it, it's yours</p>
                        </div>
                    </label>
                </div>

                <div x-show="offerType === 'lend'" x-cloak>
                    <label class="field-label mb-2">Exchange rate</label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="credit_type" value="gift" x-model="creditType" class="sr-only">
                            <div :class="creditType === 'gift' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                                 class="border-2 rounded-lg p-3 text-center text-sm font-medium transition-colors">Free</div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="credit_type" value="time_equal" x-model="creditType" class="sr-only">
                            <div :class="creditType === 'time_equal' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                                 class="border-2 rounded-lg p-3 text-center text-sm font-medium transition-colors">Time</div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="credit_type" value="custom" x-model="creditType" class="sr-only">
                            <div :class="creditType === 'custom' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                                 class="border-2 rounded-lg p-3 text-center text-sm font-medium transition-colors">Custom</div>
                        </label>
                    </div>
                    <div x-show="creditType === 'custom'" x-cloak class="mt-3">
                        <input type="number" name="custom_credit_value"
                               value="{{ old('custom_credit_value', $item->custom_credit_value) }}"
                               step="0.25" min="0" class="w-40 field" placeholder="Hours">
                    </div>
                </div>
                @error('offer_type')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                @error('credit_type')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="btn-primary px-6 py-2.5 text-sm">Save Changes</button>
                <a href="{{ route('items.show', $item) }}" class="btn-ghost text-sm">Cancel</a>
            </div>
        </form>

        <form method="POST" action="{{ route('items.destroy', $item) }}"
              onsubmit="return confirm('Delete this item permanently?')"
              class="mt-4">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-500 hover:text-red-700 font-medium transition-colors">
                Delete this item
            </button>
        </form>
    </div>
</x-app-layout>
