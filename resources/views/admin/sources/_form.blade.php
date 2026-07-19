@csrf
<div class="space-y-4 max-w-xl">
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $source->name ?? '')" required />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="url" value="URL" />
        <x-text-input id="url" name="url" class="mt-1 block w-full" :value="old('url', $source->url ?? '')" required />
        <x-input-error :messages="$errors->get('url')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="type" value="Type" />
        <select id="type" name="type" class="mt-1 block w-full rounded-md border-forest-pale">
            <option value="">Select a type</option>
            @foreach (\App\Models\Source::TYPES as $t)
                <option value="{{ $t }}" @selected(old('type', $source->type ?? '') === $t)>{{ ucfirst($t) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('type')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="organization_id" value="Organization (optional)" />
        <select id="organization_id" name="organization_id" class="mt-1 block w-full rounded-md border-forest-pale">
            <option value="">None</option>
            @foreach (\App\Models\Organization::orderBy('name')->get() as $org)
                <option value="{{ $org->id }}" @selected(old('organization_id', $source->organization_id ?? '') == $org->id)>{{ $org->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('organization_id')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="selector_config" value="Selector Config (JSON, optional)" />
        <textarea id="selector_config" name="selector_config" rows="4" class="mt-1 block w-full rounded-md border-forest-pale">{{ old('selector_config', (isset($source) && $source->selector_config) ? json_encode($source->selector_config, JSON_PRETTY_PRINT) : '') }}</textarea>
        <x-input-error :messages="$errors->get('selector_config')" class="mt-1" />
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $source->active ?? true)) /> Active</label>
    <x-primary-button>Save</x-primary-button>
</div>
