@extends('layouts.public')
@section('title', 'Events')
@section('meta')
    <meta name="description" content="Upcoming community events in Lake City, Seattle.">
@endsection
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="font-display text-3xl text-forest">Events</h1>
        <div class="flex items-center gap-3 text-sm">
            <a class="text-forest underline" href="{{ route('events.index', array_merge(request()->only('organization'), ['view' => 'month'])) }}">Month view</a>
            <a class="text-forest underline" href="{{ route('events.ics') }}">Subscribe (.ics)</a>
        </div>
    </div>

    <form method="GET" class="mb-6">
        <select name="organization" onchange="this.form.submit()" class="rounded-md border-forest-pale text-sm">
            <option value="">All organizations</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->slug }}" @selected(request('organization') === $org->slug)>{{ $org->name }}</option>
            @endforeach
        </select>
    </form>

    @forelse ($eventsByDay as $day => $events)
        <section class="mb-6">
            <h2 class="font-display text-lg text-forest mb-2">{{ \Carbon\Carbon::parse($day)->format('l, F j') }}</h2>
            <div class="space-y-3">
                @foreach ($events as $event)
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="flex items-baseline justify-between gap-4">
                            <h3 class="font-semibold">@if($event->url)<a class="text-forest" href="{{ $event->url }}" rel="noopener">{{ $event->title }}</a>@else{{ $event->title }}@endif</h3>
                            <span class="text-sm text-earth-muted whitespace-nowrap">{{ $event->starts_at->format('g:i A') }}</span>
                        </div>
                        @if ($event->organization)<p class="text-xs text-earth-muted mt-0.5">{{ $event->organization->name }}</p>@endif
                        @if ($event->location)<p class="text-sm mt-1">{{ $event->location }}</p>@endif
                        @if ($event->description)<p class="text-sm text-earth-muted mt-1">{{ \Illuminate\Support\Str::limit($event->description, 200) }}</p>@endif
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="text-center py-10">
            @include('partials.grove-empty')
            <p class="text-earth-muted">No upcoming events yet — <a class="text-forest underline" href="{{ route('submissions.create') }}">submit one</a>.</p>
        </div>
    @endforelse
@endsection
