<x-app-layout>
    @section('title', 'Requests')

    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="font-display text-2xl font-semibold text-earth mb-6">Exchange Requests</h1>

        <div class="space-y-8">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-earth-muted mb-3">Received</h2>
                @forelse($received as $req)
                    <a href="{{ route('requests.show', $req) }}"
                       class="flex items-center justify-between bg-white rounded-card px-5 py-4 shadow-sm hover:shadow-md border border-transparent hover:border-forest-pale transition-all mb-3 block">
                        <div>
                            <p class="font-medium text-earth text-sm">{{ $req->requester->name }}</p>
                            <p class="text-xs text-earth-muted">{{ ucfirst($req->resource_type) }} &middot; {{ $req->proposed_datetime->format('M j, Y g:ia') }}</p>
                        </div>
                        @php $colors = ['pending'=>'bg-amber text-white','accepted'=>'bg-forest-light text-white','completed'=>'bg-earth text-white','declined'=>'bg-red-100 text-red-700','cancelled'=>'bg-gray-100 text-gray-500','in_progress'=>'bg-forest text-white']; @endphp
                        <span class="text-xs font-semibold px-3 py-1 rounded-full {{ $colors[$req->status] ?? '' }}">{{ ucfirst(str_replace('_',' ',$req->status)) }}</span>
                    </a>
                @empty
                    <p class="text-earth-muted text-sm">No received requests.</p>
                @endforelse
            </div>

            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-earth-muted mb-3">Sent</h2>
                @forelse($sent as $req)
                    <a href="{{ route('requests.show', $req) }}"
                       class="flex items-center justify-between bg-white rounded-card px-5 py-4 shadow-sm hover:shadow-md border border-transparent hover:border-forest-pale transition-all mb-3 block">
                        <div>
                            <p class="font-medium text-earth text-sm">{{ $req->owner->name }}</p>
                            <p class="text-xs text-earth-muted">{{ ucfirst($req->resource_type) }} &middot; {{ $req->proposed_datetime->format('M j, Y g:ia') }}</p>
                        </div>
                        <span class="text-xs font-semibold px-3 py-1 rounded-full {{ $colors[$req->status] ?? '' }}">{{ ucfirst(str_replace('_',' ',$req->status)) }}</span>
                    </a>
                @empty
                    <p class="text-earth-muted text-sm">You haven't sent any requests.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
