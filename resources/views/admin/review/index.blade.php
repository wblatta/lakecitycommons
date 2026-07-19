<x-app-layout>
    @section('title', 'Review Queue')

    <div class="max-w-4xl mx-auto px-4 py-8">
        @if(session('error'))
            <div class="bg-red-50 border-b border-red-100 text-red-700 px-4 py-2.5 text-sm rounded-lg mb-6 font-medium">
                {{ session('error') }}
            </div>
        @endif

        @if ($failingSources->isNotEmpty())
            <div class="rounded-lg bg-amber-50 border border-amber-300 text-amber-900 px-4 py-3 text-sm mb-6">
                <p class="font-semibold">Sources failing repeatedly:</p>
                <ul class="mt-1 list-disc list-inside">
                    @foreach ($failingSources as $failing)
                        <li><a class="underline" href="{{ route('admin.sources.edit', $failing) }}">{{ $failing->name }}</a> — {{ $failing->consecutive_failures }} consecutive failures</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <h1 class="font-display text-2xl text-forest mb-6">Review Queue</h1>

        <h2 class="font-display text-lg text-forest mb-3">Submissions ({{ $submissions->count() }})</h2>
        @forelse ($submissions as $submission)
            <div class="bg-white rounded-lg p-4 shadow-sm mb-3">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <span class="text-xs uppercase tracking-wide text-earth-muted">{{ $submission->type }}</span>
                        <h3 class="font-semibold">{{ $submission->title }}</h3>
                        <p class="text-sm text-earth-muted">From {{ $submission->submitter_name }} ({{ $submission->submitter_email }}) · {{ $submission->created_at->diffForHumans() }}</p>
                        <p class="text-sm mt-2 whitespace-pre-line">{{ $submission->body }}</p>
                        @if ($submission->type === 'event' && $submission->event_fields)
                            <p class="text-sm mt-1 text-forest">
                                @if (!empty($submission->event_fields['starts_at']))
                                    {{ \Carbon\Carbon::parse($submission->event_fields['starts_at'])->format('D M j, g:i A') }}
                                    @if ($submission->event_fields['location'] ?? null) · {{ $submission->event_fields['location'] }}@endif
                                @else
                                    No date provided
                                @endif
                            </p>
                            @if (!empty($submission->event_fields['url']))
                                <p class="text-sm mt-1">
                                    <span class="text-xs text-earth-muted break-all">{{ $submission->event_fields['url'] }}</span>
                                </p>
                            @endif
                        @endif
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.review.submissions.approve', $submission) }}">@csrf<x-primary-button>Approve</x-primary-button></form>
                        <form method="POST" action="{{ route('admin.review.submissions.reject', $submission) }}">@csrf<x-danger-button>Reject</x-danger-button></form>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-earth-muted mb-6">No pending submissions.</p>
        @endforelse

        <h2 class="font-display text-lg text-forest mt-8 mb-3">Pending events ({{ $pendingEvents->count() }})</h2>
        @forelse ($pendingEvents as $event)
            <div class="bg-white rounded-lg p-4 shadow-sm mb-3 flex items-start justify-between gap-4">
                <div>
                    <h3 class="font-semibold">{{ $event->title }}</h3>
                    <p class="text-sm text-forest">{{ $event->starts_at->format('D M j, g:i A') }}@if($event->location) · {{ $event->location }}@endif</p>
                    @if ($event->description)<p class="text-sm text-earth-muted mt-1">{{ \Illuminate\Support\Str::limit($event->description, 200) }}</p>@endif
                </div>
                <div class="flex gap-2 shrink-0">
                    <form method="POST" action="{{ route('admin.review.events.approve', $event) }}">@csrf<x-primary-button>Approve</x-primary-button></form>
                    <form method="POST" action="{{ route('admin.review.events.reject', $event) }}">@csrf<x-danger-button>Reject</x-danger-button></form>
                </div>
            </div>
        @empty
            <p class="text-earth-muted">No pending events.</p>
        @endforelse
    </div>
</x-app-layout>
