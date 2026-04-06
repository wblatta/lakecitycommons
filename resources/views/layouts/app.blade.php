<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'OlyHillsHub') }} — @yield('title', 'Community Exchange')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-cream font-sans text-earth antialiased">

    {{-- Desktop Top Nav --}}
    <nav class="hidden md:flex items-center justify-between px-6 py-4 bg-white border-b border-forest-pale sticky top-0 z-40">
        <a href="{{ route('dashboard') }}" class="font-display text-xl font-semibold text-forest">OlyHillsHub</a>

        <div class="flex items-center gap-6 text-sm font-medium text-earth-muted">
            <a href="{{ route('skills.index') }}" class="hover:text-forest transition-colors">Skills</a>
            <a href="{{ route('items.index') }}" class="hover:text-forest transition-colors">Items</a>
            <a href="{{ route('requests.index') }}" class="hover:text-forest transition-colors">Requests</a>
            <a href="{{ route('messages.index') }}" class="relative hover:text-forest transition-colors"
               x-data="{ unread: 0 }"
               x-init="setInterval(() => fetch('/messages/unread-count').then(r => r.json()).then(d => unread = d.count), 15000)">
                Messages
                <span x-show="unread > 0" x-text="unread"
                      class="absolute -top-2 -right-3 bg-amber text-white text-xs rounded-full px-1.5 py-0.5 font-semibold"></span>
            </a>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.members.index') }}" class="hover:text-forest transition-colors">Admin</a>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <span class="text-xs font-semibold text-forest bg-forest-pale rounded-full px-3 py-1">
                {{ number_format(auth()->user()->time_bank_balance, 1) }} hrs
            </span>
            <a href="{{ route('profile.edit') }}" class="text-sm font-medium text-earth-muted hover:text-forest transition-colors">
                {{ auth()->user()->name }}
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-xs text-earth-muted hover:text-forest transition-colors">Sign out</button>
            </form>
        </div>
    </nav>

    {{-- Main content --}}
    <main class="pb-20 md:pb-0 min-h-screen">
        @if(session('success'))
            <div class="bg-forest-pale text-forest-dark px-4 py-3 text-sm text-center font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 text-red-700 px-4 py-3 text-sm text-center">
                {{ $errors->first() }}
            </div>
        @endif

        {{ $slot }}
    </main>

    {{-- Mobile Bottom Tab Bar --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white border-t border-forest-pale z-40 flex items-center justify-around py-2 px-2">
        <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-0.5 text-xs font-medium text-earth-muted hover:text-forest transition-colors min-w-[44px] py-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline stroke-linecap="round" stroke-linejoin="round" points="9 22 9 12 15 12 15 22"/></svg>
            Home
        </a>
        <a href="{{ route('skills.index') }}" class="flex flex-col items-center gap-0.5 text-xs font-medium text-earth-muted hover:text-forest transition-colors min-w-[44px] py-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M12 8v4l3 3"/></svg>
            Browse
        </a>
        <a href="{{ route('requests.index') }}" class="flex flex-col items-center gap-0.5 text-xs font-medium text-earth-muted hover:text-forest transition-colors min-w-[44px] py-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
            Requests
        </a>
        <a href="{{ route('messages.index') }}" class="relative flex flex-col items-center gap-0.5 text-xs font-medium text-earth-muted hover:text-forest transition-colors min-w-[44px] py-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Messages
        </a>
        <a href="{{ route('profile.edit') }}" class="flex flex-col items-center gap-0.5 text-xs font-medium text-earth-muted hover:text-forest transition-colors min-w-[44px] py-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profile
        </a>
    </nav>
</body>
</html>
