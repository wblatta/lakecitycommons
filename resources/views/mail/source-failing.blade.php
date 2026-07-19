<p>The source <strong>{{ $source->name }}</strong> has failed {{ $source->consecutive_failures }} consecutive fetch runs.</p>
<p>URL: {{ $source->url }}</p>
<p>Last success: {{ $source->last_succeeded_at?->toDayDateTimeString() ?? 'never' }}</p>
<p><a href="{{ route('admin.sources.edit', $source) }}">Edit this source</a></p>
