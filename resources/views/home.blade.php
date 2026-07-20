@extends('layouts.public')

@section('meta')
    <meta name="description" content="Neighborhood news, events, and organizations for Lake City, Seattle.">
@endsection

@section('content')
    <div class="rounded-card overflow-hidden bg-white shadow-sm mb-10">
        @include('partials.grove-hero')
        <div class="text-center px-6 pt-8 pb-10">
            <h1 class="font-display text-4xl md:text-5xl font-semibold text-forest">Lake City Commons</h1>
            <p class="mt-4 text-lg text-earth-muted max-w-2xl mx-auto">
                One place for Lake City, Seattle — the weekly news digest, a neighborhood events
                calendar, and a directory of the organizations that make this place work.
            </p>
            <div class="mt-8 flex justify-center gap-4">
                <a href="{{ route('news.index') }}" class="px-5 py-2.5 rounded-lg bg-forest text-white font-medium">Read the news</a>
                <a href="{{ route('events.index') }}" class="px-5 py-2.5 rounded-lg border border-forest text-forest font-medium">See events</a>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-8 mt-4">
        <section>
            <h2 class="font-display text-xl text-forest mb-4">Latest news</h2>
            @forelse ($posts as $post)
                <article class="bg-white rounded-lg p-4 shadow-sm mb-3">
                    <h3 class="font-semibold"><a class="text-forest" href="{{ route('news.show', $post) }}">{{ $post->title }}</a></h3>
                    <p class="text-xs text-earth-muted mt-1">{{ $post->published_at->format('F j, Y') }}</p>
                </article>
            @empty
                <div class="text-center py-4">
                    @include('partials.grove-empty')
                    <p class="text-sm text-earth-muted">First digest coming soon.</p>
                </div>
            @endforelse
        </section>
        <section>
            <h2 class="font-display text-xl text-forest mb-4">Coming up</h2>
            @forelse ($events as $event)
                <div class="bg-white rounded-lg p-4 shadow-sm mb-3">
                    <h3 class="font-semibold">{{ $event->title }}</h3>
                    <p class="text-xs text-earth-muted mt-1">{{ $event->starts_at->format('D M j, g:i A') }}@if($event->location) · {{ $event->location }}@endif</p>
                </div>
            @empty
                <div class="text-center py-4">
                    @include('partials.grove-empty')
                    <p class="text-sm text-earth-muted">No events yet — <a class="text-forest underline" href="{{ route('submissions.create') }}">submit one</a>.</p>
                </div>
            @endforelse
        </section>
    </div>
@endsection
