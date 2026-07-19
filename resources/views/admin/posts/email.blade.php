<x-app-layout>
    @section('title', 'Email version — ' . $post->title)
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="font-display text-2xl text-forest mb-2">Email version</h1>
        <p class="text-sm text-earth-muted mb-6">
            Select everything inside the box below (click, then Ctrl/Cmd-A) and paste it into a
            new email. Suggested subject: <strong>{{ $post->title }}</strong>
        </p>

        <div class="bg-white rounded-lg shadow-sm p-6 mb-8" id="email-body">
            <div style="font-family: Georgia, 'Times New Roman', serif; font-size: 16px; line-height: 1.6; color: #1f2937; max-width: 640px;">
                <h1 style="font-size: 22px; margin-bottom: 4px;">{{ $post->title }}</h1>
                <p style="font-size: 13px; color: #6b7280; margin-top: 0;">
                    From <a href="{{ route('news.show', $post) }}">lakecitycommons.com</a>
                </p>
                {!! $html !!}
                <hr style="margin-top: 24px; border: none; border-top: 1px solid #d1d5db;">
                <p style="font-size: 13px; color: #6b7280;">
                    Read online: <a href="{{ route('news.show', $post) }}">{{ route('news.show', $post) }}</a>
                </p>
            </div>
        </div>

        <h2 class="font-display text-lg text-forest mb-2">Plain-text (markdown) source</h2>
        <textarea readonly rows="14" class="w-full rounded-md border-forest-pale font-mono text-xs"
                  onclick="this.select()">{{ $post->body }}</textarea>
    </div>
</x-app-layout>
