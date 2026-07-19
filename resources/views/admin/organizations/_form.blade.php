@csrf
<div class="space-y-4 max-w-xl">
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $organization->name ?? '')" required />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="category" value="Category" />
        <select id="category" name="category" class="mt-1 block w-full rounded-md border-forest-pale">
            @foreach (\App\Models\Organization::CATEGORIES as $cat)
                <option value="{{ $cat }}" @selected(old('category', $organization->category ?? '') === $cat)>{{ ucfirst($cat) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-forest-pale">{{ old('description', $organization->description ?? '') }}</textarea>
    </div>
    @foreach ([['website', 'Website'], ['email', 'Email'], ['phone', 'Phone'], ['address', 'Address']] as [$field, $label])
        <div>
            <x-input-label :for="$field" :value="$label" />
            <x-text-input :id="$field" :name="$field" class="mt-1 block w-full" :value="old($field, $organization->$field ?? '')" />
            <x-input-error :messages="$errors->get($field)" class="mt-1" />
        </div>
    @endforeach
    <div>
        <x-input-label for="logo" value="Logo (optional)" />
        <input type="file" id="logo" name="logo" accept="image/*" class="mt-1 block w-full text-sm" />
        <x-input-error :messages="$errors->get('logo')" class="mt-1" />
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $organization->active ?? true)) /> Active (shown in directory)</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_sponsor" value="1" @checked(old('is_sponsor', $organization->is_sponsor ?? false)) /> Sponsor</label>
    <div>
        <x-input-label for="sponsor_tier" value="Sponsor tier (optional)" />
        <x-text-input id="sponsor_tier" name="sponsor_tier" class="mt-1 block w-full" :value="old('sponsor_tier', $organization->sponsor_tier ?? '')" />
    </div>
    <x-primary-button>Save</x-primary-button>
</div>
