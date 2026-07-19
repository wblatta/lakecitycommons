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
            <h2 class="font-display text-xl text-forest border-b border-forest-pale/60 pb-2 mb-4">{{ $label }}</h2>
            <div class="grid md:grid-cols-2 gap-4">
                @foreach ($groups[$key] as $org)
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            @if ($logo = $org->getFirstMediaUrl('logo'))
                                <img src="{{ $logo }}" alt="" class="w-10 h-10 rounded object-cover">
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
        <p class="text-earth-muted">The directory is just getting started — <a class="text-forest underline" href="{{ route('submissions.create') }}">tell us about your organization</a>.</p>
    @endif
@endsection
