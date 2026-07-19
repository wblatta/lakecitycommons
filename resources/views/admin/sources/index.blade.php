<x-app-layout>
    @section('title', 'Sources')

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="font-display text-2xl font-semibold text-earth">Sources</h1>
            <a href="{{ route('admin.sources.create') }}" class="btn-primary px-4 py-2 text-sm">+ Add source</a>
        </div>

        @if($sources->isEmpty())
            <div class="bg-white rounded-card shadow-sm p-10 text-center text-earth-muted">
                No sources yet. <a href="{{ route('admin.sources.create') }}" class="text-forest hover:underline">Create the first one.</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-earth-muted border-b border-forest-pale/60">
                        <th class="py-2">Name</th><th>Type</th><th>Org</th><th>Last success</th><th>Health</th><th></th>
                    </tr></thead>
                    <tbody>
                    @foreach ($sources as $source)
                        <tr class="border-b border-forest-pale/30">
                            <td class="py-2 font-medium">{{ $source->name }}</td>
                            <td>{{ ucfirst($source->type) }}</td>
                            <td>{{ $source->organization?->name ?? '—' }}</td>
                            <td>{{ $source->last_succeeded_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                @if($source->consecutive_failures >= 2)
                                    <span class="inline-block bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs font-semibold">{{ $source->consecutive_failures }} failures</span>
                                @else
                                    <span class="inline-block bg-forest-pale text-forest px-2 py-1 rounded-full text-xs font-semibold">ok</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a class="text-forest underline" href="{{ route('admin.sources.edit', $source) }}">Edit</a>
                                <form class="inline" method="POST" action="{{ route('admin.sources.destroy', $source) }}" onsubmit="return confirm('Delete {{ $source->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-700 underline ml-2">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $sources->links() }}</div>
        @endif
    </div>
</x-app-layout>
