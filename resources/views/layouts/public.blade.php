<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') — @endif{{ config('app.name') }}</title>
    @yield('meta')
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.png'))">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-cream font-sans text-earth antialiased">
    <nav class="flex items-center justify-between px-4 md:px-8 bg-white border-b border-forest-pale/60 sticky top-0 z-40 shadow-sm">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5 py-3">
            <svg class="w-9 h-9 shrink-0" viewBox="0 0 64 64" role="img" aria-hidden="true">
                <circle cx="32" cy="32" r="30" fill="#1B4332"/>
                <circle cx="44" cy="14" r="4" fill="#D4A017"/>
                <polygon points="18,24 25,40 11,40" fill="#52B788"/>
                <polygon points="18,31 26,48 10,48" fill="#52B788"/>
                <rect x="16.75" y="48" width="2.5" height="4" fill="#B7E4C7"/>
                <polygon points="46,24 53,40 39,40" fill="#52B788"/>
                <polygon points="46,31 54,48 38,48" fill="#52B788"/>
                <rect x="44.75" y="48" width="2.5" height="4" fill="#B7E4C7"/>
                <polygon points="32,12 40,34 24,34" fill="#B7E4C7"/>
                <polygon points="32,22 42,44 22,44" fill="#B7E4C7"/>
                <rect x="30.5" y="44" width="3" height="6" fill="#F8F9F4"/>
            </svg>
            <span class="font-display text-xl font-semibold text-forest">{{ config('app.name') }}</span>
        </a>
        <div class="flex items-center gap-1 text-sm font-medium">
            @foreach ([['url' => route('news.index'), 'label' => 'News', 'is' => 'news*'], ['url' => route('events.index'), 'label' => 'Events', 'is' => 'events*'], ['url' => route('directory.index'), 'label' => 'Directory', 'is' => 'directory*'], ['url' => route('submissions.create'), 'label' => 'Submit', 'is' => 'submit*']] as $link)
                <a href="{{ $link['url'] }}"
                   class="px-3 md:px-4 py-5 border-b-2 transition-colors {{ request()->is($link['is']) ? 'border-forest text-forest font-semibold' : 'border-transparent text-earth-muted hover:text-forest hover:border-forest-pale' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
            @auth
                <a href="{{ config('features.community') ? route('dashboard') : route('admin.posts.index') }}" class="px-3 py-5 text-earth-muted hover:text-forest">Account</a>
            @endauth
        </div>
    </nav>

    @if (session('success'))
        <div class="max-w-4xl mx-auto mt-4 px-4"><div class="rounded-lg bg-forest-pale/40 text-forest px-4 py-3 text-sm">{{ session('success') }}</div></div>
    @endif

    <main class="max-w-4xl mx-auto px-4 py-8">
        @yield('content')
    </main>

    <div class="mt-16">@include('partials.grove-treeline')</div>
    <footer class="border-t border-forest-pale/60 bg-white">
        <div class="max-w-4xl mx-auto px-4 py-8 text-sm text-earth-muted space-y-2">
            <p class="font-display text-forest text-base">{{ config('app.name') }}</p>
            <p>Neighborhood news, events, and organizations for Lake City, Seattle — in one place.</p>
            @if (config('services.buttondown.signup_url'))
                <p><a class="text-forest underline" href="{{ config('services.buttondown.signup_url') }}">Get the weekly digest by email →</a></p>
            @endif
        </div>
    </footer>
</body>
</html>
