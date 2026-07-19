<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') — @endif{{ config('app.name') }}</title>
    @yield('meta')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-cream font-sans text-earth antialiased">
    <nav class="flex items-center justify-between px-4 md:px-8 bg-white border-b border-forest-pale/60 sticky top-0 z-40 shadow-sm">
        <a href="{{ url('/') }}" class="font-display text-xl font-semibold text-forest py-4">{{ config('app.name') }}</a>
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

    <footer class="border-t border-forest-pale/60 bg-white mt-16">
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
