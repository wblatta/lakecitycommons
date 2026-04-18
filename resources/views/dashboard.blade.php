<x-app-layout>
    @section('title', 'Dashboard')

    {{-- Gradient hero --}}
    <div class="bg-gradient-to-br from-forest-dark via-forest to-forest-light text-white relative overflow-hidden">
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
                <div class="w-11 h-11 rounded-xl bg-forest-pale flex items-center justify-center mb-3 group-hover:bg-forest transition-colors">
                    <svg class="w-5 h-5 text-forest group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636-.707.707M21 12h-1M4 12H3m3.343-5.657-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <p class="text-sm font-semibold text-earth">Browse Skills</p>
                <p class="text-xs text-earth-muted mt-0.5">Neighbours helping neighbours</p>
            </a>
            <a href="{{ route('items.index') }}" class="card card-hover p-5 flex flex-col items-center text-center group">
                <div class="w-11 h-11 rounded-xl bg-forest-pale flex items-center justify-center mb-3 group-hover:bg-forest transition-colors">
                    <svg class="w-5 h-5 text-forest group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <p class="text-sm font-semibold text-earth">Browse Items</p>
                <p class="text-xs text-earth-muted mt-0.5">Tools, gear, and more</p>
            </a>
            <a href="{{ route('messages.index') }}" class="card card-hover p-5 flex flex-col items-center text-center group">
                <div class="w-11 h-11 rounded-xl bg-forest-pale flex items-center justify-center mb-3 group-hover:bg-forest transition-colors">
                    <svg class="w-5 h-5 text-forest group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
                <p class="text-sm font-semibold text-earth">Messages</p>
                <p class="text-xs text-earth-muted mt-0.5">Chat with neighbours</p>
            </a>
            <a href="{{ route('invite.index') }}" class="card card-hover p-5 flex flex-col items-center text-center group">
                <div class="w-11 h-11 rounded-xl bg-forest-pale flex items-center justify-center mb-3 group-hover:bg-forest transition-colors">
                    <svg class="w-5 h-5 text-forest group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM3 20a6 6 0 0 1 12 0v1H3v-1z"/></svg>
                </div>
                <p class="text-sm font-semibold text-earth">Invite Neighbor</p>
                <p class="text-xs text-earth-muted mt-0.5">Grow the community</p>
            </a>
        </div>

        {{-- Announcements --}}
        @if($announcements->isNotEmpty())
            <div>
                <h2 class="font-display text-xl font-semibold text-earth mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-forest" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                    Announcements
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.posts.index') }}"
                           class="ml-auto text-xs font-medium text-earth-muted hover:text-forest transition-colors">
                            Manage posts
                        </a>
                    @endif
                </h2>
                <div class="space-y-3">
                    @foreach($announcements as $post)
                        <div class="bg-white rounded-card shadow-sm border-l-4 border-forest p-5">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-semibold text-earth">{{ $post->title }}</h3>
                                <span class="text-xs text-earth-muted flex-shrink-0">{{ $post->published_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-earth-muted mt-2 leading-relaxed whitespace-pre-line">{{ $post->body }}</p>
                            <p class="text-xs text-earth-muted mt-3">Posted by {{ $post->user->name }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif(auth()->user()->isAdmin())
            <div class="bg-white rounded-card shadow-sm border-2 border-dashed border-forest-pale p-6 text-center">
                <p class="text-earth-muted text-sm mb-2">No announcements yet.</p>
                <a href="{{ route('admin.posts.create') }}" class="text-forest text-sm font-medium hover:underline">Post the first announcement</a>
            </div>
        @endif

        {{-- Requests --}}
        <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-white rounded-card p-6 shadow-sm">
                <h2 class="font-display text-lg font-semibold text-earth mb-4">Requests for you</h2>
                @forelse($pendingForMe as $req)
                    <a href="{{ route('requests.show', $req) }}"
                       class="flex items-center justify-between py-3 border-b border-cream last:border-0 hover:bg-cream -mx-2 px-2 rounded transition-colors">
                        <div>
                            <p class="text-sm font-medium text-earth">{{ $req->requester->name }}</p>
                            <p class="text-xs text-earth-muted">
                                {{ ucfirst($req->resource_type) }} &middot; {{ $req->proposed_datetime->format('M j, g:ia') }}
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

            <div class="bg-white rounded-card p-6 shadow-sm">
                <h2 class="font-display text-lg font-semibold text-earth mb-4">Your requests</h2>
                @forelse($recentRequests as $req)
                    <a href="{{ route('requests.show', $req) }}"
                       class="flex items-center justify-between py-3 border-b border-cream last:border-0 hover:bg-cream -mx-2 px-2 rounded transition-colors">
                        <div>
                            <p class="text-sm font-medium text-earth">{{ $req->owner->name }}</p>
                            <p class="text-xs text-earth-muted">
                                {{ ucfirst($req->resource_type) }} &middot; {{ $req->proposed_datetime->format('M j, g:ia') }}
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

        {{-- Community Activity Feed --}}
        <div>
            <h2 class="font-display text-xl font-semibold text-earth mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-forest" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Community Activity
            </h2>

            @if($activity->isEmpty())
                <div class="bg-white rounded-card shadow-sm p-8 text-center text-earth-muted text-sm">
                    No recent activity yet — be the first to offer a skill or list an item!
                </div>
            @else
                <div class="bg-white rounded-card shadow-sm divide-y divide-cream">
                    @foreach($activity as $event)
                        <div class="flex items-start gap-3 p-4 hover:bg-cream/50 transition-colors">
                            {{-- Avatar --}}
                            @if(isset($event['user']))
                                @if($event['user']->avatar)
                                    <img src="{{ $event['user']->avatarUrl() }}" alt="{{ $event['user']->name }}"
                                         class="w-8 h-8 rounded-full object-cover flex-shrink-0 mt-0.5">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-forest-pale flex items-center justify-center text-forest font-bold text-xs flex-shrink-0 mt-0.5">
                                        {{ $event['user']->initials() }}
                                    </div>
                                @endif
                            @endif

                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-earth">
                                    {{ $event['label'] }}
                                    @if($event['url'])
                                        — <a href="{{ $event['url'] }}" class="text-forest font-medium hover:underline">{{ $event['detail'] }}</a>
                                    @elseif($event['detail'])
                                        <span class="text-earth-muted">· {{ $event['detail'] }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-earth-muted mt-0.5">{{ $event['created_at']->diffForHumans() }}</p>
                            </div>

                            {{-- Type icon --}}
                            <div class="flex-shrink-0 mt-0.5">
                                @if($event['type'] === 'skill')
                                    <svg class="w-4 h-4 text-forest-light" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636-.707.707M21 12h-1M4 12H3m3.343-5.657-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                @elseif($event['type'] === 'item')
                                    <svg class="w-4 h-4 text-forest-light" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @elseif($event['type'] === 'exchange')
                                    <svg class="w-4 h-4 text-amber" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($event['type'] === 'member')
                                    <svg class="w-4 h-4 text-earth-muted" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
