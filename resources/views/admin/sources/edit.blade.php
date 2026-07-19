<x-app-layout>
    @section('title', 'Edit Source')

    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="font-display text-2xl font-semibold text-earth mb-6">Edit {{ $source->name }}</h1>
        <form method="POST" action="{{ route('admin.sources.update', $source) }}">
            @method('PUT')
            @include('admin.sources._form')
        </form>
    </div>
</x-app-layout>
