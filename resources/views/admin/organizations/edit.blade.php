<x-app-layout>
    @section('title', 'Edit Organization')

    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="font-display text-2xl font-semibold text-earth mb-6">Edit {{ $organization->name }}</h1>
        <form method="POST" action="{{ route('admin.organizations.update', $organization) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('admin.organizations._form')
        </form>
    </div>
</x-app-layout>
