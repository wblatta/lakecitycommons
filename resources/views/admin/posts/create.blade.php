<x-app-layout>
    @section('title', 'New Post')

    <div class="max-w-2xl mx-auto px-4 py-8">
        <a href="{{ route('admin.posts.index') }}" class="text-forest text-sm hover:underline mb-4 inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to posts
        </a>
        <h1 class="font-display text-2xl font-semibold text-earth mb-6">New Post</h1>

        <form method="POST" action="{{ route('admin.posts.store') }}" class="bg-white rounded-card p-6 shadow-sm space-y-5">
            @csrf

            <div>
                <label class="field-label">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" required class="field"
                       placeholder="e.g. Community Clean-up this Saturday">
                @error('title')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Body</label>
                <textarea name="body" rows="8" required class="field resize-y"
                          placeholder="Write your announcement here…">{{ old('body') }}</textarea>
                @error('body')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-cream">
                <button type="submit" name="published" value="1" class="btn-primary px-5 py-2.5 text-sm">
                    Publish Now
                </button>
                <button type="submit" name="published" value="0" class="btn-outline px-5 py-2.5 text-sm">
                    Save as Draft
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
