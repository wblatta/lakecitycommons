<x-app-layout>
    @section('title', 'Dashboard')

    {{-- Gradient hero --}}
    <div class="bg-gradient-to-br from-forest-dark via-forest to-forest-light text-white relative overflow-hidden">
        {{-- Decorative circles --}}
        <div class="absolute inset-0 pointer-events-none select-none">
            <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-white/5"></div>
            <div class="absolute top-8 right-24 w-32 h-32 rounded-full bg-white/5"></div>
            <div class="absolute -bottom-8 left-12 w-48 h-48 rounded-full bg-white/5"></div>
        </div>

        <div class="max-w-5xl mx-auto px-4 py-8 relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5">
            <div>
                <p class="text-forest-pale text-sm font-medium mb-1">Welcome back, {{ auth()->user()->name }}</p>
                <div class="flex items-baseline gap-2">
                    <span class="font-display text-5xl font-semibold tabular-nums">
                        {{ number_format($user->time_bank_balance, 1) }}
                    </span>
                    <span class="text-2xl font-normal text-forest-pale">hrs</span>
                </div>
                <p class="text-forest-pale/70 text-xs mt-1">Time bank balance</p>
                @if($user->time_bank_balance < 1)
                    <p class="text-amber text-xs mt-2 font-medium bg-amber/10 rounded-full px-3 py-1 inline-block">
                        Balance is low — consider completing an exchange.
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('skills.create') }}"
                   class="px-4 py-2 bg-white text-forest font-semibold text-sm rounded-lg hover:bg-forest-pale transition-colors">
                    + Offer Skill
                </a>
                <a href="{{ route('items.create') }}"
                   class="px-4 py-2 bg-white/20 text-white font-semibold text-sm rounded-lg hover:bg-white/30 transition-colors border border-white/20">
                    + List Item
                </a>
                <a href="{{ route('invite.index') }}"
                   class="px-4 py-2 border border-white/30 text-white font-semibold text-sm rounded-lg hover:bg-white/10 transition-colors">
                    Invite Neighbor
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-8 space-y-8">

        {{-- Quick links --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('skills.index') }}" class="card card-hover p-5 flex flex-col items-center text-center group">
                <div class="w-11 h-11 rounded-xl bg-forest-pale flex items-center justify-center mb-3 group-hover:bg-forest group-hover:text-white transition-colors">
                    <svg class="w-5 h-5 text-forest group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636-.707.707M21 12h-1M4 12H3m3.343-5.657-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <p class="text-sm font-semibold text-earth">Browse Skills</p>
                <p class="text-xs text-earth-muted mt-0.5">Neighbours helping neighbours</p>
            </a>
            <a href="{{ route('items.index') }}" class="card card-hover p-5 flex flex-col items-center text-center group">
                <div class="w-11 h-11 rounded-xl bg-forest-pale flex items-center justify-center mb-3 group-hover:bg-forest group-hover:text-white transition-colors">
                    <svg class="w-5 h-5 text-forest group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <p class="text-sm font-semibold text-earth">Browse Items</p>
                <p class="text-xs text-earth-muted mt-0.5">Tools, gear, and more</p>
            </a>
            <a href="{{ route('messages.index') }}" class="card card-hover p-5 flex flex-col items-center text-center group">
                <div class="w-11 h-11 rounded-xl bg-forest-pale flex items-center justify-center mb-3 group-hover:bg-forest group-hover:text-white transition-colors">
                    <svg class="w-5 h-5 text-forest group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
                <p class="text-sm font-semibold text-earth">Messages</p>
                <p class="text-xs text-earth-muted mt-0.5">Chat with neighbours</p>
            </a>
            <a href="{{ route('invite.index') }}" class="card card-hover p-5 flex flex-col items-center text-center group">
                <div class="w-11 h-11 rounded-xl bg-forest-pale flex items-center justify-center mb-3 group-hover:bg-forest group-hover:text-white transition-colors">
                    <svg class="w-5 h-5 text-forest group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM3 20a6 6 0 0 1 12 0v1H3v-1z"/></svg>
                </div>
                <p class="text-sm font-semibold text-earth">Invite Neighbor</p>
                <p class="text-xs text-earth-muted mt-0.5">Grow the community</p>
            </a>
        </div>

        <div class="grid md:grid-cols-2 gap-6">

            {{-- Pending requests for me --}}
            <div class="bg-white rounded-card p-6 shadow-sm">
                <h2 class="font-display text-lg font-semibold text-earth mb-4">Requests for you</h2>
                @forelse($pendingForMe as $req)
                    <a href="{{ route('requests.show', $req) }}"
                       class="flex items-center justify-between py-3 border-b border-cream last:border-0 hover:bg-cream -mx-2 px-2 rounded transition-colors">
                        <div>
                            <p class="text-sm font-medium text-earth">{{ $req->requester->name }}</p>
                            <p class="text-xs text-earth-muted">
                                {{ ucfirst($req->resource_type) }} &middot;
                                {{ $req->proposed_datetime->format('M j, g:ia') }}
                            </p>
                        </div>
                        <span class="badge-pending">Pending</span>
                    </a>
                @empty
                    <p class="text-earth-muted text-sm">No pending requests.</p>
                @endforelse
                @if($pendingForMe->count())
                    <a href="{{ route('requests.index') }}" class="text-forest text-sm font-medium mt-4 inline-block hover:underline">View all</a>
                @endif
            </div>

            {{-- My recent requests --}}
            <div class="bg-white rounded-card p-6 shadow-sm">
                <h2 class="font-display text-lg font-semibold text-earth mb-4">Your requests</h2>
                @forelse($recentRequests as $req)
                    <a href="{{ route('requests.show', $req) }}"
                       class="flex items-center justify-between py-3 border-b border-cream last:border-0 hover:bg-cream -mx-2 px-2 rounded transition-colors">
                        <div>
                            <p class="text-sm font-medium text-earth">{{ $req->owner->name }}</p>
                            <p class="text-xs text-earth-muted">
                                {{ ucfirst($req->resource_type) }} &middot;
                                {{ $req->proposed_datetime->format('M j, g:ia') }}
                            </p>
                        </div>
                        @php
                            $statusClass = match($req->status) {
                                'pending'     => 'badge-pending',
                                'accepted'    => 'badge-accepted',
                                'in_progress' => 'badge-accepted',
                                'completed'   => 'badge-completed',
                                'declined'    => 'badge-declined',
                                'cancelled'   => 'badge-cancelled',
                                default       => 'badge bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <span class="{{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $req->status)) }}</span>
                    </a>
                @empty
                    <p class="text-earth-muted text-sm">You haven't made any requests yet.</p>
                @endforelse
                <a href="{{ route('skills.index') }}" class="text-forest text-sm font-medium mt-4 inline-block hover:underline">Browse skills &amp; items</a>
            </div>
        </div>
    </div>
</x-app-layout>
