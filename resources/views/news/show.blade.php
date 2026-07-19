@extends('layouts.public')
@section('title', $post->title)
@section('meta')
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($post->body), 160) }}">
    <meta property="og:title" content="{{ $post->title }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ route('news.show', $post) }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($post->body), 160) }}">
@endsection
@section('content')
    <article class="bg-white rounded-lg p-6 md:p-10 shadow-sm">
        <h1 class="font-display text-3xl text-forest">{{ $post->title }}</h1>
        <p class="text-xs text-earth-muted mt-2">{{ $post->published_at->format('F j, Y') }}</p>
        <div class="prose prose-sm mt-6 max-w-none whitespace-pre-line">{{ $post->body }}</div>
    </article>
    <p class="mt-6"><a class="text-forest underline" href="{{ route('news.index') }}">← All news</a></p>
@endsection
