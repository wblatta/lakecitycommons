@extends('layouts.public')
@section('title', 'Events — ' . $month->format('F Y'))
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="font-display text-3xl text-forest">{{ $month->format('F Y') }}</h1>
        <div class="flex items-center gap-3 text-sm">
            <a class="text-forest underline" href="{{ route('events.index', array_merge(request()->only('organization'), ['view' => 'month', 'month' => $month->copy()->subMonth()->format('Y-m')])) }}">← {{ $month->copy()->subMonth()->format('M') }}</a>
            <a class="text-forest underline" href="{{ route('events.index', array_merge(request()->only('organization'), ['view' => 'month', 'month' => $month->copy()->addMonth()->format('Y-m')])) }}">{{ $month->copy()->addMonth()->format('M') }} →</a>
            <a class="text-forest underline" href="{{ route('events.index', request()->only('organization')) }}">List view</a>
        </div>
    </div>

    <div class="grid grid-cols-7 gap-px bg-forest-pale/40 rounded-lg overflow-hidden text-xs">
        @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
            <div class="bg-white p-2 font-semibold text-earth-muted text-center">{{ $dow }}</div>
        @endforeach
        @for ($day = $gridStart->copy(); $day <= $gridEnd; $day->addDay())
            <div class="bg-white p-2 min-h-24 {{ $day->month === $month->month ? '' : 'opacity-40' }}">
                <div class="text-earth-muted">{{ $day->day }}</div>
                @foreach ($eventsByDay->get($day->format('Y-m-d'), collect()) as $event)
                    <div class="mt-1 rounded bg-forest-pale/40 px-1 py-0.5 text-forest truncate" title="{{ $event->title }}">
                        {{ $event->starts_at->format('g:ia') }} {{ $event->title }}
                    </div>
                @endforeach
            </div>
        @endfor
    </div>
@endsection
