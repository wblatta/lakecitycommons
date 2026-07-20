@extends('layouts.public')
@section('title', 'Directory')
@section('meta')
    <meta name="description" content="Directory of Lake City, Seattle organizations, services, and businesses.">
@endsection
@section('content')
    <h1 class="font-display text-3xl text-forest mb-8">Lake City Directory</h1>
    @forelse ($labels as $key => $label)
        @continue(!isset($groups[$key]))
        <section class="mb-10">
            <h2 class="font-display text-xl text-forest border-b border-forest-pale/60 pb-2 mb-4 flex items-center gap-2">
                <span class="w-5 h-5 text-forest/80">@include('partials.category-icon', ['category' => $key])</span>{{ $label }}
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
                @foreach ($groups[$key] as $org)
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            @if ($logo = $org->getFirstMediaUrl('logo'))
                                <img src="{{ $logo }}" alt="" class="w-10 h-10 rounded-lg object-contain bg-white ring-1 ring-forest-pale/60 shrink-0">
                            @else
                                @php
                                    $initials = collect(preg_split('/[\s—-]+/', $org->name))
                                        ->filter(fn ($w) => preg_match('/^\p{L}/u', $w) && ! in_array(mb_strtolower($w), ['the', 'of', 'and', 'at', 'a']))
                                        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                                        ->take(2)->implode('');
                                @endphp
                                <div class="w-10 h-10 rounded-full bg-forest-pale/70 flex items-center justify-center text-forest-dark font-display font-semibold text-sm shrink-0">{{ $initials }}</div>
                            @endif
                            <h3 class="font-semibold">{{ $org->name }}</h3>
                        </div>
                        @if ($org->description)<p class="mt-2 text-sm text-earth-muted">{{ $org->description }}</p>@endif
                        <div class="mt-2 text-sm space-x-3">
                            @if ($org->website)<a class="text-forest underline" href="{{ $org->website }}" rel="noopener">Website</a>@endif
                            @if ($org->email)<a class="text-forest underline" href="mailto:{{ $org->email }}">Email</a>@endif
                            @if ($org->phone)<span>{{ $org->phone }}</span>@endif
                        </div>
                        @if ($org->address)<p class="mt-1 text-xs text-earth-muted">{{ $org->address }}</p>@endif
                    </div>
                @endforeach
            </div>
        </section>
    @empty
    @endforelse
    @if ($groups->isEmpty())
        <div class="text-center py-8">
            @include('partials.grove-empty')
            <p class="text-earth-muted">The directory is just getting started — <a class="text-forest underline" href="{{ route('submissions.create') }}">tell us about your organization</a>.</p>
        </div>
    @endif
@endsection
