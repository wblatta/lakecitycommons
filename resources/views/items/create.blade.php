<x-app-layout>
    @section('title', 'List an Item')

    <div class="max-w-2xl mx-auto px-4 py-8">
        <a href="{{ route('items.index') }}" class="text-forest text-sm hover:underline mb-4 inline-block">&larr; Back to items</a>
        <h1 class="font-display text-2xl font-semibold text-earth mb-6">List an Item</h1>

        <form method="POST" action="{{ route('items.store') }}" enctype="multipart/form-data"
              class="bg-white rounded-card p-6 shadow-sm space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-earth mb-1.5">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" required
                       class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest"
                       placeholder="e.g. Ladder, Stand mixer, Camping tent">
                @error('title')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-earth mb-1.5">Description</label>
                <textarea name="description" rows="3" required
                          class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest resize-none">{{ old('description') }}</textarea>
                @error('description')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-earth mb-1.5">Category</label>
                    <select name="category_id" required class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest bg-white">
                        <option value="">Select</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-earth mb-1.5">Condition</label>
                    <select name="condition" required class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest bg-white">
                        @foreach(['excellent', 'good', 'fair', 'poor'] as $c)
                            <option value="{{ $c }}" {{ old('condition') === $c ? 'selected' : '' }}>{{ ucfirst($c) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div x-data="{ offerType: '{{ old('offer_type', 'lend') }}', creditType: '{{ old('credit_type', 'gift') }}' }">
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
                    <label class="block text-sm font-medium text-earth mb-2">Exchange rate</label>
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
                        <input type="number" name="custom_credit_value" step="0.25" min="0" placeholder="Hours"
                               value="{{ old('custom_credit_value') }}"
                               class="w-40 px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest">
                    </div>
                </div>
                @error('offer_type')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                @error('credit_type')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-earth mb-1.5">Photos <span class="text-earth-muted font-normal">(up to 5, JPG/PNG/WebP, max 5MB each)</span></label>
                <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"
                       class="w-full text-sm text-earth-muted file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-forest-pale file:text-forest hover:file:bg-forest hover:file:text-white transition-colors">
            </div>

            <button type="submit" class="w-full bg-forest text-white font-semibold py-3 rounded-lg hover:bg-forest-dark transition-colors">
                List Item
            </button>
        </form>
    </div>
</x-app-layout>
