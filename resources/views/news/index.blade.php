@extends('layouts.public')
@section('title', 'News')
@section('meta')
    <meta name="description" content="News and the weekly digest for Lake City, Seattle.">
@endsection
@section('content')
    <h1 class="font-display text-3xl text-forest mb-8">News</h1>
    <div class="space-y-6">
        @forelse ($posts as $post)
            <article class="bg-white rounded-lg p-6 shadow-sm">
                <h2 class="font-display text-xl"><a class="text-forest" href="{{ route('news.show', $post) }}">{{ $post->title }}</a></h2>
                <p class="text-xs text-earth-muted mt-1">{{ $post->published_at->format('F j, Y') }}</p>
                <p class="text-sm text-earth-muted mt-2">{{ \Illuminate\Support\Str::limit(strip_tags($post->body), 240) }}</p>
            </article>
        @empty
            <p class="text-earth-muted">No posts yet — the first weekly digest is coming soon.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $posts->links() }}</div>
@endsection
