@extends('layouts.public')
@section('title', 'Submit')
@section('content')
    <h1 class="font-display text-3xl text-forest mb-2">Submit an event or announcement</h1>
    <p class="text-earth-muted mb-6">Submissions are reviewed before they appear on the site or in the weekly digest.</p>

    <form method="POST" action="{{ route('submissions.store') }}" class="space-y-4 max-w-xl" x-data="{ type: '{{ old('type', 'event') }}' }">
        @csrf
        <div class="hidden" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <div>
            <x-input-label value="What are you submitting?" />
            <select name="type" x-model="type" class="mt-1 block w-full rounded-md border-forest-pale">
                <option value="event">Event</option>
                <option value="announcement">Announcement</option>
            </select>
        </div>
        <div>
            <x-input-label for="submitter_name" value="Your name" />
            <x-text-input id="submitter_name" name="submitter_name" class="mt-1 block w-full" :value="old('submitter_name')" required />
            <x-input-error :messages="$errors->get('submitter_name')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="submitter_email" value="Your email (not published)" />
            <x-text-input id="submitter_email" name="submitter_email" type="email" class="mt-1 block w-full" :value="old('submitter_email')" required />
            <x-input-error :messages="$errors->get('submitter_email')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="title" value="Title" />
            <x-text-input id="title" name="title" class="mt-1 block w-full" :value="old('title')" required />
            <x-input-error :messages="$errors->get('title')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="body" value="Details" />
            <textarea id="body" name="body" rows="5" class="mt-1 block w-full rounded-md border-forest-pale" required>{{ old('body') }}</textarea>
            <x-input-error :messages="$errors->get('body')" class="mt-1" />
        </div>
        <template x-if="type === 'event'">
            <div class="space-y-4">
                <div>
                    <x-input-label for="starts_at" value="Date & time" />
                    <input type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at') }}" class="mt-1 block w-full rounded-md border-forest-pale">
                    <x-input-error :messages="$errors->get('starts_at')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="location" value="Location" />
                    <x-text-input id="location" name="location" class="mt-1 block w-full" :value="old('location')" />
                </div>
                <div>
                    <x-input-label for="url" value="Link for more info (optional)" />
                    <x-text-input id="url" name="url" class="mt-1 block w-full" :value="old('url')" />
                    <x-input-error :messages="$errors->get('url')" class="mt-1" />
                </div>
            </div>
        </template>
        <x-primary-button>Submit for review</x-primary-button>
    </form>
@endsection
