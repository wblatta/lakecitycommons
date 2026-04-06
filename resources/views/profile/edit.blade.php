<x-app-layout>
    @section('title', 'My Profile')

    <div class="max-w-4xl mx-auto px-4 py-8"
         x-data="{ tab: '{{ session('status') === 'profile-updated' || $errors->any() ? 'profile' : (request('tab') === 'listings' ? 'listings' : 'profile') }}' }">

        {{-- Page header --}}
        <div class="flex items-center gap-4 mb-6">
            @if(auth()->user()->avatar)
                <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}"
                     class="w-16 h-16 rounded-full object-cover ring-2 ring-forest-pale">
            @else
                <div class="w-16 h-16 rounded-full bg-forest-pale flex items-center justify-center text-forest font-bold text-xl">
                    {{ auth()->user()->initials() }}
                </div>
            @endif
            <div>
                <h1 class="font-display text-2xl font-semibold text-earth">{{ auth()->user()->name }}</h1>
                <p class="text-earth-muted text-sm">{{ auth()->user()->neighborhood_area }}</p>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex border-b border-forest-pale/60 mb-6">
            <button @click="tab = 'profile'"
                    :class="tab === 'profile' ? 'border-forest text-forest font-semibold' : 'border-transparent text-earth-muted hover:text-forest'"
                    class="px-5 py-3 border-b-2 text-sm font-medium transition-colors">
                My Profile
            </button>
            <button @click="tab = 'listings'"
                    :class="tab === 'listings' ? 'border-forest text-forest font-semibold' : 'border-transparent text-earth-muted hover:text-forest'"
                    class="px-5 py-3 border-b-2 text-sm font-medium transition-colors">
                My Listings
                @php $totalListings = $skills->count() + $items->count(); @endphp
                @if($totalListings > 0)
                    <span class="ml-1.5 bg-forest-pale text-forest text-xs font-bold rounded-full px-1.5 py-0.5">{{ $totalListings }}</span>
                @endif
            </button>
        </div>

        {{-- MY PROFILE TAB --}}
        <div x-show="tab === 'profile'" x-cloak>
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                {{-- Avatar upload --}}
                <div class="bg-white rounded-card shadow-sm p-6">
                    <h2 class="font-display text-lg font-semibold text-earth mb-4">Photo</h2>
                    <div class="flex items-center gap-5"
                         x-data="{ preview: '{{ auth()->user()->avatar ? auth()->user()->avatarUrl() : '' }}' }">
                        <div class="relative flex-shrink-0">
                            <template x-if="preview">
                                <img :src="preview" class="w-20 h-20 rounded-full object-cover ring-2 ring-forest-pale">
                            </template>
                            <template x-if="!preview">
                                <div class="w-20 h-20 rounded-full bg-forest-pale flex items-center justify-center text-forest font-bold text-2xl">
                                    {{ auth()->user()->initials() }}
                                </div>
                            </template>
                        </div>
                        <div>
                            <label for="avatar" class="btn-outline px-4 py-2 text-sm cursor-pointer inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Choose photo
                            </label>
                            <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="hidden"
                                   @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview">
                            <p class="text-xs text-earth-muted mt-1.5">JPG, PNG or WebP, max 3MB</p>
                            @error('avatar') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Basic info --}}
                <div class="bg-white rounded-card shadow-sm p-6 space-y-4">
                    <h2 class="font-display text-lg font-semibold text-earth mb-2">Basic Information</h2>

                    <div>
                        <label for="name" class="field-label">Full Name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', auth()->user()->name) }}"
                               class="field" required autocomplete="name">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="email" class="field-label">Email Address</label>
                        <input id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}"
                               class="field" required autocomplete="email">
                        @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        @if(auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !auth()->user()->hasVerifiedEmail())
                            <p class="text-xs text-amber mt-1">Your email is unverified.
                                <form id="send-verification" method="POST" action="{{ route('verification.send') }}" class="inline">@csrf</form>
                                <button form="send-verification" class="underline hover:text-forest">Resend verification</button>
                            </p>
                        @endif
                    </div>

                    <div>
                        <label for="bio" class="field-label">About Me <span class="text-earth-muted font-normal">(optional)</span></label>
                        <textarea id="bio" name="bio" rows="3" maxlength="500"
                                  class="field resize-none"
                                  placeholder="Share a bit about yourself, your interests, what you love about the neighbourhood…">{{ old('bio', auth()->user()->bio) }}</textarea>
                        @error('bio') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="neighborhood_area" class="field-label">Neighbourhood Area</label>
                            <input id="neighborhood_area" name="neighborhood_area" type="text"
                                   value="{{ old('neighborhood_area', auth()->user()->neighborhood_area) }}"
                                   class="field" placeholder="e.g. Eastside, Olympia Hills">
                            @error('neighborhood_area') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="cross_streets" class="field-label">Cross Streets</label>
                            <input id="cross_streets" name="cross_streets" type="text"
                                   value="{{ old('cross_streets', auth()->user()->cross_streets) }}"
                                   class="field" placeholder="e.g. Oak St & Maple Ave">
                            @error('cross_streets') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary px-6 py-2.5 text-sm">Save Profile</button>
                </div>
            </form>

            {{-- Change Password --}}
            <div class="bg-white rounded-card shadow-sm p-6 mt-6" x-data="{ open: false }">
                <button @click="open = !open"
                        class="w-full flex items-center justify-between text-left">
                    <h2 class="font-display text-lg font-semibold text-earth">Change Password</h2>
                    <svg class="w-5 h-5 text-earth-muted transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-collapse class="mt-4">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- Danger zone --}}
            <div class="bg-white rounded-card shadow-sm p-6 mt-6 border border-red-100">
                <h2 class="font-display text-lg font-semibold text-red-700 mb-1">Danger Zone</h2>
                <p class="text-sm text-earth-muted mb-4">Permanently delete your account and all of your data.</p>
                @include('profile.partials.delete-user-form')
            </div>
        </div>

        {{-- MY LISTINGS TAB --}}
        <div x-show="tab === 'listings'" x-cloak>

            {{-- Skills --}}
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-display text-xl font-semibold text-earth">My Skills</h2>
                    <a href="{{ route('skills.create') }}" class="btn-primary px-4 py-2 text-sm">+ Offer Skill</a>
                </div>

                @forelse($skills as $skill)
                    <div class="bg-white rounded-card shadow-sm border border-transparent p-4 mb-3 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <a href="{{ route('skills.show', $skill) }}"
                                   class="font-medium text-earth hover:text-forest truncate">{{ $skill->title }}</a>
                                @if($skill->is_available)
                                    <span class="badge-available flex-shrink-0">Available</span>
                                @else
                                    <span class="badge-hold flex-shrink-0">On Hold</span>
                                    @php $waitCount = $skill->waitlistEntries()->count(); @endphp
                                    @if($waitCount > 0)
                                        <span class="badge bg-amber/20 text-amber flex-shrink-0">{{ $waitCount }} waiting</span>
                                    @endif
                                @endif
                            </div>
                            <p class="text-xs text-earth-muted">{{ $skill->category->name }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a href="{{ route('skills.edit', $skill) }}"
                               class="text-xs font-medium text-earth-muted hover:text-forest px-3 py-1.5 rounded-lg hover:bg-forest-pale/50 transition-colors">
                                Edit
                            </a>
                            <form method="POST" action="{{ route('skills.toggle', $skill) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors {{ $skill->is_available ? 'text-amber hover:bg-amber/10' : 'text-forest hover:bg-forest-pale/50' }}">
                                    {{ $skill->is_available ? 'Place on Hold' : 'Make Available' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('skills.destroy', $skill) }}"
                                  onsubmit="return confirm('Delete this skill? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="bg-white rounded-card shadow-sm p-8 text-center">
                        <p class="text-earth-muted text-sm mb-3">You haven't offered any skills yet.</p>
                        <a href="{{ route('skills.create') }}" class="btn-primary px-5 py-2 text-sm">Offer a Skill</a>
                    </div>
                @endforelse
            </div>

            {{-- Items --}}
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-display text-xl font-semibold text-earth">My Items</h2>
                    <a href="{{ route('items.create') }}" class="btn-primary px-4 py-2 text-sm">+ List Item</a>
                </div>

                @forelse($items as $item)
                    <div class="bg-white rounded-card shadow-sm border border-transparent p-4 mb-3 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <a href="{{ route('items.show', $item) }}"
                                   class="font-medium text-earth hover:text-forest truncate">{{ $item->title }}</a>
                                @if($item->is_available)
                                    <span class="badge-available flex-shrink-0">Available</span>
                                @else
                                    <span class="badge-hold flex-shrink-0">On Hold</span>
                                    @php $waitCount = $item->waitlistEntries()->count(); @endphp
                                    @if($waitCount > 0)
                                        <span class="badge bg-amber/20 text-amber flex-shrink-0">{{ $waitCount }} waiting</span>
                                    @endif
                                @endif
                            </div>
                            <p class="text-xs text-earth-muted">{{ $item->category->name }} &middot; {{ ucfirst($item->condition) }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a href="{{ route('items.edit', $item) }}"
                               class="text-xs font-medium text-earth-muted hover:text-forest px-3 py-1.5 rounded-lg hover:bg-forest-pale/50 transition-colors">
                                Edit
                            </a>
                            <form method="POST" action="{{ route('items.toggle', $item) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors {{ $item->is_available ? 'text-amber hover:bg-amber/10' : 'text-forest hover:bg-forest-pale/50' }}">
                                    {{ $item->is_available ? 'Place on Hold' : 'Make Available' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('items.destroy', $item) }}"
                                  onsubmit="return confirm('Delete this item? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="bg-white rounded-card shadow-sm p-8 text-center">
                        <p class="text-earth-muted text-sm mb-3">You haven't listed any items yet.</p>
                        <a href="{{ route('items.create') }}" class="btn-primary px-5 py-2 text-sm">List an Item</a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
