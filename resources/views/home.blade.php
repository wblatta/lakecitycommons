@extends('layouts.public')

@section('meta')
    <meta name="description" content="Neighborhood news, events, and organizations for Lake City, Seattle.">
@endsection

@section('content')
    <div class="text-center py-12">
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
@endsection
