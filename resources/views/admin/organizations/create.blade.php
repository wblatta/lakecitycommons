<x-app-layout>
    @section('title', 'New Organization')

    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="font-display text-2xl font-semibold text-earth mb-6">New Organization</h1>
        <form method="POST" action="{{ route('admin.organizations.store') }}" enctype="multipart/form-data">
            @include('admin.organizations._form')
        </form>
    </div>
</x-app-layout>
