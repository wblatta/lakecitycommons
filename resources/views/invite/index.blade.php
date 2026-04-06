<x-app-layout>
    @section('title', 'Invite a Neighbor')

    <div class="max-w-2xl mx-auto px-4 py-8">
        <h1 class="font-display text-2xl font-semibold text-earth mb-2">Invite a Neighbor</h1>
        <p class="text-earth-muted text-sm mb-8">OlyHillsHub is invitation-only. Share a link with someone you trust in the neighborhood.</p>

        <div class="bg-white rounded-card p-6 shadow-sm mb-6">
            <form method="POST" action="{{ route('invite.store') }}" class="flex gap-3">
                @csrf
                <input type="email" name="invitee_email" placeholder="Their email (optional)"
                       class="flex-1 px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest text-sm">
                <button type="submit"
                        class="px-5 py-3 bg-forest text-white font-semibold rounded-lg hover:bg-forest-dark transition-colors text-sm whitespace-nowrap">
                    Generate Link
                </button>
            </form>

            @if(session('link'))
                <div class="mt-4 p-4 bg-forest-pale rounded-lg">
                    <p class="text-xs text-forest-dark font-semibold mb-1 uppercase tracking-wide">Your invitation link</p>
                    <div class="flex items-center gap-2">
                        <input type="text" value="{{ session('link') }}" readonly
                               class="flex-1 text-sm bg-white px-3 py-2 rounded border border-forest-pale font-mono"
                               onclick="this.select()">
                        <button onclick="navigator.clipboard.writeText('{{ session('link') }}')"
                                class="px-3 py-2 bg-forest text-white text-xs font-semibold rounded hover:bg-forest-dark transition-colors">
                            Copy
                        </button>
                    </div>
                    <p class="text-xs text-earth-muted mt-2">Expires in 30 days. Can only be used once.</p>
                </div>
            @endif
        </div>

        @if($tokens->isNotEmpty())
            <h2 class="font-display text-lg font-semibold text-earth mb-3">Your invitations</h2>
            <div class="bg-white rounded-card shadow-sm divide-y divide-cream">
                @foreach($tokens as $token)
                    <div class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p class="text-sm font-medium text-earth">{{ $token->invitee_email ?? 'Open invitation' }}</p>
                            <p class="text-xs text-earth-muted">Expires {{ $token->expires_at->format('M j, Y') }}</p>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $token->used_at ? 'bg-earth text-white' : ($token->expires_at->isPast() ? 'bg-gray-100 text-gray-500' : 'bg-forest-pale text-forest') }}">
                            {{ $token->used_at ? 'Used' : ($token->expires_at->isPast() ? 'Expired' : 'Active') }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
