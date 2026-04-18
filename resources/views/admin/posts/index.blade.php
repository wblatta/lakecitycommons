<x-app-layout>
    @section('title', 'News Posts')

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="font-display text-2xl font-semibold text-earth">News &amp; Announcements</h1>
            <a href="{{ route('admin.posts.create') }}" class="btn-primary px-4 py-2 text-sm">+ New Post</a>
        </div>

        @if($posts->isEmpty())
            <div class="bg-white rounded-card shadow-sm p-10 text-center text-earth-muted">
                No posts yet. <a href="{{ route('admin.posts.create') }}" class="text-forest hover:underline">Create the first one.</a>
            </div>
        @else
            <div class="space-y-3">
                @foreach($posts as $post)
                    <div class="bg-white rounded-card shadow-sm p-5 flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h2 class="font-semibold text-earth truncate">{{ $post->title }}</h2>
                                @if($post->isPublished())
                                    <span class="badge-available flex-shrink-0">Published</span>
                                @else
                                    <span class="badge bg-gray-100 text-gray-500 flex-shrink-0">Draft</span>
                                @endif
                            </div>
                            <p class="text-sm text-earth-muted line-clamp-1">{{ $post->body }}</p>
                            <p class="text-xs text-earth-muted mt-1">
                                By {{ $post->user->name }} &middot;
                                @if($post->isPublished())
                                    Published {{ $post->published_at->diffForHumans() }}
                                @else
                                    Last updated {{ $post->updated_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a href="{{ route('admin.posts.edit', $post) }}"
                               class="text-xs font-medium text-earth-muted hover:text-forest px-3 py-1.5 rounded-lg hover:bg-forest-pale/50 transition-colors">
                                Edit
                            </a>
                            <form method="POST" action="{{ route('admin.posts.destroy', $post) }}"
                                  onsubmit="return confirm('Delete this post?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">{{ $posts->links() }}</div>
        @endif
    </div>
</x-app-layout>
