<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OlyHillsHub — Neighborhood Exchange</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-cream font-sans text-earth antialiased">
    <div class="min-h-screen flex flex-col md:flex-row">

        {{-- Left: Illustration panel --}}
        <div class="md:w-1/2 bg-forest flex flex-col justify-between p-10 md:p-16">
            <div>
                <h1 class="font-display text-3xl md:text-4xl font-semibold text-white leading-tight">OlyHillsHub</h1>
                <p class="text-forest-pale text-sm mt-1 tracking-wide">Olympia Hills Neighborhood Exchange</p>
            </div>

            <div class="my-10 md:my-0 flex-1 flex items-center justify-center">
                <svg viewBox="0 0 400 320" class="w-full max-w-sm opacity-90" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <ellipse cx="200" cy="290" rx="180" ry="20" fill="#1B4332" opacity="0.4"/>
                    <rect x="80" y="200" width="10" height="90" rx="3" fill="#1B4332"/>
                    <ellipse cx="85" cy="185" rx="35" ry="45" fill="#52B788"/>
                    <ellipse cx="85" cy="170" rx="25" ry="35" fill="#B7E4C7"/>
                    <rect x="305" y="210" width="10" height="80" rx="3" fill="#1B4332"/>
                    <ellipse cx="310" cy="195" rx="30" ry="40" fill="#52B788"/>
                    <ellipse cx="310" cy="180" rx="22" ry="30" fill="#B7E4C7"/>
                    <rect x="155" y="230" width="7" height="60" rx="2" fill="#1B4332"/>
                    <ellipse cx="158" cy="220" rx="20" ry="28" fill="#52B788"/>
                    <rect x="130" y="190" width="50" height="50" rx="4" fill="white" opacity="0.9"/>
                    <polygon points="130,190 155,165 180,190" fill="#B7E4C7"/>
                    <rect x="145" y="215" width="20" height="25" rx="2" fill="#52B788" opacity="0.6"/>
                    <rect x="240" y="200" width="45" height="45" rx="4" fill="white" opacity="0.9"/>
                    <polygon points="240,200 262,178 285,200" fill="#B7E4C7"/>
                    <rect x="252" y="223" width="18" height="22" rx="2" fill="#52B788" opacity="0.6"/>
                    <path d="M 155 215 Q 200 180 262 222" stroke="#B7E4C7" stroke-width="2" stroke-dasharray="6,4" opacity="0.7"/>
                    <circle cx="155" cy="215" r="8" fill="#D4A017"/>
                    <circle cx="262" cy="222" r="8" fill="#D4A017"/>
                    <circle cx="200" cy="155" r="6" fill="white" opacity="0.8"/>
                    <path d="M 155 215 Q 178 185 200 155" stroke="#B7E4C7" stroke-width="1.5" stroke-dasharray="4,3" opacity="0.5"/>
                    <path d="M 262 222 Q 232 190 200 155" stroke="#B7E4C7" stroke-width="1.5" stroke-dasharray="4,3" opacity="0.5"/>
                </svg>
            </div>

            <div class="text-forest-pale text-sm space-y-2 max-w-xs">
                <p class="font-semibold text-white">Share skills. Swap items. Build community.</p>
                <p class="opacity-80">A time bank for our neighborhood — by invitation only.</p>
            </div>
        </div>

        {{-- Right: Login form --}}
        <div class="md:w-1/2 flex flex-col justify-center px-8 py-12 md:px-16">
            <div class="max-w-sm w-full mx-auto">
                <h2 class="font-display text-2xl font-semibold text-earth mb-1">Welcome back</h2>
                <p class="text-earth-muted text-sm mb-8">Sign in to your neighborhood account.</p>

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium text-earth mb-1.5">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="w-full px-4 py-3 rounded-lg border border-forest-pale bg-white text-earth placeholder-earth-muted focus:outline-none focus:ring-2 focus:ring-forest focus:border-transparent transition"
                               placeholder="you@example.com">
                        @error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-earth mb-1.5">Password</label>
                        <input id="password" type="password" name="password" required
                               class="w-full px-4 py-3 rounded-lg border border-forest-pale bg-white text-earth placeholder-earth-muted focus:outline-none focus:ring-2 focus:ring-forest focus:border-transparent transition"
                               placeholder="••••••••••">
                        @error('password')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 text-earth-muted cursor-pointer">
                            <input type="checkbox" name="remember" class="rounded border-forest-pale text-forest focus:ring-forest">
                            Remember me
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-forest hover:underline">Forgot password?</a>
                        @endif
                    </div>

                    <button type="submit"
                            class="w-full bg-forest text-white font-semibold py-3 rounded-lg hover:bg-forest-dark transition-colors focus:outline-none focus:ring-2 focus:ring-forest focus:ring-offset-2">
                        Sign in
                    </button>
                </form>

                <p class="mt-8 text-center text-sm text-earth-muted">
                    Don't have an account?
                    <span class="text-forest font-medium">You'll need an invitation from a neighbor.</span>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
