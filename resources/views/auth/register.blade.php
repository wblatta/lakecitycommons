<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join OlyHillsHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-cream font-sans text-earth antialiased">
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="font-display text-3xl font-semibold text-forest">OlyHillsHub</h1>
                <p class="text-earth-muted text-sm mt-1">You've been invited to join the neighborhood exchange.</p>
                @if($referral)
                    <p class="mt-2 text-sm font-medium text-forest">Invited by {{ $referral->inviter->name }}</p>
                @endif
            </div>

            <div class="bg-white rounded-card p-8 shadow-sm">
                <h2 class="font-display text-xl font-semibold text-earth mb-6">Create your account</h2>

                <form method="POST" action="/register/{{ $token }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-earth mb-1.5">Full Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus
                               class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest"
                               placeholder="Your name">
                        @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-earth mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email', $referral?->invitee_email) }}" required
                               class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest"
                               placeholder="you@example.com">
                        @error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-earth mb-1.5">Neighborhood Area</label>
                        <input type="text" name="neighborhood_area" value="{{ old('neighborhood_area') }}"
                               class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest"
                               placeholder="e.g. North Capitol Hill, Central District">
                        @error('neighborhood_area')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-earth mb-1.5">Password</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest"
                               placeholder="At least 10 characters, mixed case + numbers">
                        @error('password')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-earth mb-1.5">Confirm Password</label>
                        <input type="password" name="password_confirmation" required
                               class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest"
                               placeholder="Repeat your password">
                    </div>

                    <button type="submit"
                            class="w-full bg-forest text-white font-semibold py-3 rounded-lg hover:bg-forest-dark transition-colors focus:outline-none focus:ring-2 focus:ring-forest focus:ring-offset-2 mt-2">
                        Create Account
                    </button>
                </form>

                <p class="text-xs text-earth-muted text-center mt-6">
                    Already have an account?
                    <a href="{{ route('login') }}" class="text-forest hover:underline font-medium">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
