{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ config('app.name') }}</title>
    <link>{{ url('/') }}</link>
    <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml"/>
    <description>Neighborhood news, events, and organizations for Lake City, Seattle.</description>
    <language>en-us</language>
    @foreach ($posts as $post)
    <item>
        <title>{{ $post->title }}</title>
        <link>{{ route('news.show', $post) }}</link>
        <guid isPermaLink="true">{{ route('news.show', $post) }}</guid>
        <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
        <description>{{ \Illuminate\Support\Str::limit(strip_tags($post->body), 500) }}</description>
    </item>
    @endforeach
</channel>
</rss>
