<x-app-layout>
    @section('title', 'Organizations')

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="font-display text-2xl font-semibold text-earth">Organizations</h1>
            <a href="{{ route('admin.organizations.create') }}" class="btn-primary px-4 py-2 text-sm">+ Add organization</a>
        </div>

        @if($organizations->isEmpty())
            <div class="bg-white rounded-card shadow-sm p-10 text-center text-earth-muted">
                No organizations yet. <a href="{{ route('admin.organizations.create') }}" class="text-forest hover:underline">Create the first one.</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-earth-muted border-b border-forest-pale/60">
                        <th class="py-2">Name</th><th>Category</th><th>Sponsor</th><th>Active</th><th></th>
                    </tr></thead>
                    <tbody>
                    @foreach ($organizations as $org)
                        <tr class="border-b border-forest-pale/30">
                            <td class="py-2 font-medium">{{ $org->name }}</td>
                            <td>{{ ucfirst($org->category) }}</td>
                            <td>{{ $org->is_sponsor ? ($org->sponsor_tier ?: 'yes') : '—' }}</td>
                            <td>{{ $org->active ? 'yes' : 'no' }}</td>
                            <td class="text-right">
                                <a class="text-forest underline" href="{{ route('admin.organizations.edit', $org) }}">Edit</a>
                                <form class="inline" method="POST" action="{{ route('admin.organizations.destroy', $org) }}" onsubmit="return confirm('Delete {{ $org->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-700 underline ml-2">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $organizations->links() }}</div>
        @endif
    </div>
</x-app-layout>
