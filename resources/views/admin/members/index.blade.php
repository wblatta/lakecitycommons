<x-app-layout>
    @section('title', 'Admin — Members')

    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="font-display text-2xl font-semibold text-earth mb-6">Member Management</h1>

        {{-- Filters --}}
        <form method="GET" class="flex gap-3 mb-6">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email..."
                   class="flex-1 px-4 py-2.5 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest text-sm">
            <select name="status" class="px-4 py-2.5 rounded-lg border border-forest-pale bg-white text-sm focus:outline-none focus:ring-2 focus:ring-forest">
                <option value="">All statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
            </select>
            <button type="submit" class="px-5 py-2.5 bg-forest text-white text-sm font-semibold rounded-lg hover:bg-forest-dark transition-colors">Filter</button>
        </form>

        <div class="bg-white rounded-card shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-cream text-earth-muted text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold">Name</th>
                        <th class="text-left px-5 py-3 font-semibold hidden md:table-cell">Email</th>
                        <th class="text-left px-5 py-3 font-semibold hidden md:table-cell">Balance</th>
                        <th class="text-left px-5 py-3 font-semibold">Status</th>
                        <th class="text-left px-5 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cream">
                    @foreach($members as $member)
                        <tr class="hover:bg-cream transition-colors">
                            <td class="px-5 py-4">
                                <p class="font-medium text-earth">{{ $member->name }}</p>
                                <p class="text-xs text-earth-muted">{{ $member->neighborhood_area }}</p>
                            </td>
                            <td class="px-5 py-4 text-earth-muted hidden md:table-cell">{{ $member->email }}</td>
                            <td class="px-5 py-4 font-medium text-earth hidden md:table-cell">{{ number_format($member->time_bank_balance, 1) }} hrs</td>
                            <td class="px-5 py-4">
                                @php $statusColors = ['active'=>'bg-forest-pale text-forest','pending'=>'bg-amber/20 text-amber','suspended'=>'bg-red-100 text-red-600']; @endphp
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusColors[$member->status] ?? '' }}">
                                    {{ ucfirst($member->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex gap-2 flex-wrap">
                                    @if($member->status !== 'active')
                                        <form method="POST" action="{{ route('admin.members.status', $member) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="active">
                                            <button class="text-xs text-forest font-semibold hover:underline">Activate</button>
                                        </form>
                                    @endif
                                    @if($member->status !== 'suspended')
                                        <form method="POST" action="{{ route('admin.members.status', $member) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="suspended">
                                            <button class="text-xs text-red-600 font-semibold hover:underline">Suspend</button>
                                        </form>
                                    @endif
                                    <button x-data x-on:click="$dispatch('open-adjust', { userId: {{ $member->id }}, name: '{{ addslashes($member->name) }}' })"
                                            class="text-xs text-earth-muted font-semibold hover:underline">Adjust Credits</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $members->withQueryString()->links() }}</div>
    </div>

    {{-- Credit Adjust Modal --}}
    <div x-data="{ open: false, userId: null, name: '' }"
         x-on:open-adjust.window="open = true; userId = $event.detail.userId; name = $event.detail.name"
         x-show="open" x-cloak
         class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-card p-6 w-full max-w-sm shadow-xl" @click.outside="open = false">
            <h2 class="font-display text-lg font-semibold text-earth mb-1">Adjust Credits</h2>
            <p class="text-sm text-earth-muted mb-4" x-text="'For: ' + name"></p>
            <form method="POST" :action="`/admin/members/${userId}/adjust-credits`" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-earth mb-1.5">Amount (+ add, - deduct)</label>
                    <input type="number" name="amount" step="0.25" required
                           class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest" placeholder="e.g. 2.0 or -1.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-earth mb-1.5">Note</label>
                    <input type="text" name="note" required
                           class="w-full px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest" placeholder="Reason for adjustment">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" @click="open = false" class="flex-1 py-2.5 text-sm font-medium text-earth-muted border border-forest-pale rounded-lg hover:bg-cream transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 py-2.5 text-sm font-semibold text-white bg-forest rounded-lg hover:bg-forest-dark transition-colors">Apply</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
