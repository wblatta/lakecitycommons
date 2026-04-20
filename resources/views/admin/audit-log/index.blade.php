<x-app-layout>
    @section('title', 'Admin Audit Log')

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="font-display text-xl font-semibold text-earth mb-6">Admin Audit Log</h2>

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-3 mb-6">
            <select name="action" class="rounded-lg border-gray-300 text-sm">
                <option value="">All actions</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>
            <select name="admin_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">All admins</option>
                @foreach($admins as $admin)
                    <option value="{{ $admin->id }}" @selected(request('admin_id') == $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-forest text-white rounded-lg text-sm">Filter</button>
            <a href="{{ route('admin.audit-log.index') }}" class="px-4 py-2 text-earth-muted text-sm">Clear</a>
        </form>

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-cream">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">When</th>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">Admin</th>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">Action</th>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">Target</th>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">Detail</th>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-earth-muted whitespace-nowrap">{{ $log->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-earth">{{ $log->admin->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-mint text-forest">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-earth">{{ $log->targetUser->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-earth-muted font-mono text-xs">{{ json_encode($log->payload) }}</td>
                            <td class="px-4 py-3 text-earth-muted text-xs">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-earth-muted">No audit log entries yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</x-app-layout>
