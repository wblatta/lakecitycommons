<x-app-layout>
    @section('title', 'Edit Post')

    <div class="max-w-2xl mx-auto px-4 py-8">
        <a href="{{ route('admin.posts.index') }}" class="text-forest text-sm hover:underline mb-4 inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to posts
        </a>
        <div class="flex items-center gap-3 mb-6">
            <h1 class="font-display text-2xl font-semibold text-earth">Edit Post</h1>
            @if($post->isPublished())
                <span class="badge-available">Published</span>
            @else
                <span class="badge bg-gray-100 text-gray-500">Draft</span>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.posts.update', $post) }}" class="bg-white rounded-card p-6 shadow-sm space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="field-label">Title</label>
                <input type="text" name="title" value="{{ old('title', $post->title) }}" required class="field">
                @error('title')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Body</label>
                <textarea name="body" rows="8" required class="field resize-y">{{ old('body', $post->body) }}</textarea>
                @error('body')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-cream">
                @if($post->isPublished())
                    <button type="submit" name="published" value="1" class="btn-primary px-5 py-2.5 text-sm">
                        Save Changes
                    </button>
                    <button type="submit" name="published" value="0" class="btn-outline px-5 py-2.5 text-sm">
                        Unpublish (save as draft)
                    </button>
                @else
                    <button type="submit" name="published" value="1" class="btn-primary px-5 py-2.5 text-sm">
                        Publish Now
                    </button>
                    <button type="submit" name="published" value="0" class="btn-outline px-5 py-2.5 text-sm">
                        Save as Draft
                    </button>
                @endif
            </div>
        </form>

        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}"
              onsubmit="return confirm('Delete this post permanently?')"
              class="mt-4">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-500 hover:text-red-700 font-medium transition-colors">
                Delete post
            </button>
        </form>
    </div>
</x-app-layout>
