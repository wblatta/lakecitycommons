<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}@hasSection('title') — @yield('title')@endif</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-cream font-sans text-earth antialiased">

    {{-- Desktop Top Nav --}}
    <nav class="hidden md:flex items-center justify-between px-8 py-0 bg-white border-b border-forest-pale/60 sticky top-0 z-40 shadow-sm">
        <a href="{{ config('features.community') ? route('dashboard') : url('/') }}" class="font-display text-xl font-semibold text-forest py-4 flex items-center gap-2">
            <svg class="w-7 h-7" viewBox="0 0 64 64" role="img" aria-hidden="true">
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
            {{ config('app.name') }}
        </a>

        <div class="flex items-center gap-1 h-full text-sm font-medium">
            @php
                $navLinks = config('features.community') ? [
                    ['route' => 'skills.index', 'label' => 'Skills'],
                    ['route' => 'items.index',  'label' => 'Items'],
                    ['route' => 'requests.index', 'label' => 'Requests'],
                ] : [];
            @endphp
            @foreach($navLinks as $link)
                <a href="{{ route($link['route']) }}"
                   class="px-4 py-5 border-b-2 transition-colors {{ request()->routeIs(str_replace('.index', '.*', $link['route'])) ? 'border-forest text-forest font-semibold' : 'border-transparent text-earth-muted hover:text-forest hover:border-forest-pale' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach

            @if(config('features.community'))
                <a href="{{ route('messages.index') }}"
                   class="relative px-4 py-5 border-b-2 transition-colors {{ request()->routeIs('messages.*') ? 'border-forest text-forest font-semibold' : 'border-transparent text-earth-muted hover:text-forest hover:border-forest-pale' }}"
                   x-data="{ unread: 0 }"
                   x-init="setInterval(() => fetch('/messages/unread-count').then(r => r.json()).then(d => unread = d.count), 15000)">
                    Messages
                    <span x-show="unread > 0" x-text="unread"
                          class="absolute top-3 right-1 bg-amber text-white text-[10px] leading-none rounded-full px-1.5 py-0.5 font-bold"></span>
                </a>
            @endif

            @if(auth()->user()->isAdmin())
                <div class="relative group" x-data="{ open: false }" @mouseenter="open=true" @mouseleave="open=false">
                    <button class="px-4 py-5 border-b-2 transition-colors flex items-center gap-1 {{ request()->routeIs('admin.*') ? 'border-forest text-forest font-semibold' : 'border-transparent text-earth-muted hover:text-forest hover:border-forest-pale' }}">
                        Admin
                        <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute right-0 top-full w-44 bg-white rounded-lg shadow-lg border border-forest-pale/60 py-1 z-50">
                        <a href="{{ route('admin.members.index') }}"
                           class="block px-4 py-2 text-sm text-earth hover:bg-cream hover:text-forest transition-colors {{ request()->routeIs('admin.members.*') ? 'text-forest font-semibold' : '' }}">
                            Members
                        </a>
                        <a href="{{ route('admin.posts.index') }}"
                           class="block px-4 py-2 text-sm text-earth hover:bg-cream hover:text-forest transition-colors {{ request()->routeIs('admin.posts.*') ? 'text-forest font-semibold' : '' }}">
                            News Posts
                        </a>
                        <a href="{{ route('admin.review.index') }}"
                           class="block px-4 py-2 text-sm text-earth hover:bg-cream hover:text-forest transition-colors {{ request()->routeIs('admin.review.*') ? 'text-forest font-semibold' : '' }}">
                            Review
                        </a>
                        <a href="{{ route('admin.organizations.index') }}"
                           class="block px-4 py-2 text-sm text-earth hover:bg-cream hover:text-forest transition-colors {{ request()->routeIs('admin.organizations.*') ? 'text-forest font-semibold' : '' }}">
                            Organizations
                        </a>
                        <a href="{{ route('admin.sources.index') }}"
                           class="block px-4 py-2 text-sm text-earth hover:bg-cream hover:text-forest transition-colors {{ request()->routeIs('admin.sources.*') ? 'text-forest font-semibold' : '' }}">
                            Sources
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3 py-4">
            {{-- Balance badge --}}
            <span class="text-xs font-semibold text-forest bg-forest-pale rounded-full px-3 py-1.5 tabular-nums">
                {{ number_format(auth()->user()->time_bank_balance, 1) }} hrs
            </span>

            {{-- Avatar + name --}}
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 group">
                @if(auth()->user()->avatar)
                    <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}"
                         class="w-8 h-8 rounded-full object-cover ring-2 ring-transparent group-hover:ring-forest-pale transition">
                @else
                    <div class="w-8 h-8 rounded-full bg-forest-pale flex items-center justify-center text-forest font-bold text-xs ring-2 ring-transparent group-hover:ring-forest transition">
                        {{ auth()->user()->initials() }}
                    </div>
                @endif
                <span class="text-sm font-medium text-earth-muted group-hover:text-forest transition">{{ auth()->user()->name }}</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-xs text-earth-muted hover:text-red-500 transition-colors px-2 py-1 rounded hover:bg-red-50">
                    Sign out
                </button>
            </form>
        </div>
    </nav>

    {{-- Flash messages --}}
    @if(session('success') || session('status') === 'profile-updated')
        <div class="bg-forest-pale/80 border-b border-forest-pale text-forest px-4 py-2.5 text-sm text-center font-medium backdrop-blur-sm">
            {{ session('success') ?? 'Profile updated.' }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border-b border-red-100 text-red-700 px-4 py-2.5 text-sm text-center">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Main content --}}
    <main class="pb-20 md:pb-0 min-h-screen">
        {{ $slot }}
    </main>

    {{-- Mobile Bottom Tab Bar --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white border-t border-forest-pale/60 z-40 flex items-center justify-around px-2 shadow-lg">
        @php
            $tabs = array_merge(
                [
                    ['route' => 'dashboard',      'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label' => 'Home'],
                ],
                config('features.community') ? [
                    ['route' => 'skills.index',   'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636-.707.707M21 12h-1M4 12H3m3.343-5.657-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', 'label' => 'Browse'],
                    ['route' => 'requests.index', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label' => 'Requests'],
                    ['route' => 'messages.index', 'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'label' => 'Messages'],
                ] : [],
                [
                    ['route' => 'profile.edit',   'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'label' => 'Profile'],
                ]
            );
        @endphp
        @foreach($tabs as $tab)
            <a href="{{ route($tab['route']) }}"
               class="flex flex-col items-center gap-0.5 py-2.5 px-3 min-w-[44px] transition-colors {{ request()->routeIs($tab['route'] === 'dashboard' ? 'dashboard' : str_replace('.index', '.*', $tab['route'])) ? 'text-forest' : 'text-earth-muted' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/>
                </svg>
                <span class="text-[10px] font-medium">{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </nav>
</body>
</html>
