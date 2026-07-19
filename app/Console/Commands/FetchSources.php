<?php

namespace App\Console\Commands;

use App\Models\ContentItem;
use App\Models\Event;
use App\Models\Source;
use App\Services\Fetchers\DatasetFetcher;
use App\Services\Fetchers\FetchedItem;
use App\Services\Fetchers\HtmlFetcher;
use App\Services\Fetchers\IcsFetcher;
use App\Services\Fetchers\RssFetcher;
use App\Services\Fetchers\SourceFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchSources extends Command
{
    protected $signature = 'app:fetch-sources';
    protected $description = 'Fetch all active content sources, dedupe into content_items, and sync events';

    public function handle(): int
    {
        foreach (Source::active()->get() as $source) {
            $source->forceFill(['last_fetched_at' => now()]);

            try {
                $items = $this->fetcherFor($source)->fetch($source);
                $this->store($source, $items);
                $source->forceFill([
                    'last_succeeded_at' => now(),
                    'consecutive_failures' => 0,
                    'failure_notified_at' => null,
                ]);
                $this->info("{$source->name}: {$items->count()} items");
            } catch (\Throwable $e) {
                $source->consecutive_failures++;
                Log::warning('Source fetch failed', ['source_id' => $source->id, 'error' => $e->getMessage()]);
                $this->warn("{$source->name}: FAILED ({$e->getMessage()})");
            }

            $source->save();
        }

        $failing = Source::active()
            ->where('consecutive_failures', '>=', 2)
            ->whereNull('failure_notified_at')
            ->get();

        if ($failing->isNotEmpty()) {
            $admins = \App\Models\User::where('role', 'admin')->pluck('email');
            foreach ($failing as $source) {
                foreach ($admins as $email) {
                    \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\SourceFailingMail($source));
                }
                $source->forceFill(['failure_notified_at' => now()])->save();
            }
        }

        return self::SUCCESS;
    }

    private function fetcherFor(Source $source): SourceFetcher
    {
        return match ($source->type) {
            'rss' => new RssFetcher,
            'ics' => new IcsFetcher,
            'html' => new HtmlFetcher,
            'dataset' => new DatasetFetcher,
            default => throw new \RuntimeException("Unknown source type: {$source->type}"),
        };
    }

    private function store(Source $source, $items): void
    {
        foreach ($items as $item) {
            /** @var FetchedItem $item */
            $isNew = ContentItem::firstOrCreate(
                ['source_id' => $source->id, 'content_hash' => $item->contentHash()],
                [
                    'url' => $item->url,
                    'title' => str($item->title)->limit(250)->toString(),
                    'summary' => $item->summary,
                    'kind' => $item->kind,
                    'published_at' => $item->publishedAt ?? $item->startsAt,
                    'fetched_at' => now(),
                ]
            )->wasRecentlyCreated;

            if ($item->kind !== 'event' || ! $item->startsAt) {
                continue;
            }

            if ($source->type === 'ics' && $item->externalUid) {
                Event::updateOrCreate(
                    ['source_id' => $source->id, 'external_uid' => $item->externalUid],
                    [
                        'title' => str($item->title)->limit(250)->toString(),
                        'description' => $item->summary,
                        'starts_at' => $item->startsAt,
                        'ends_at' => $item->endsAt,
                        'location' => $item->location,
                        'url' => $item->url,
                        'organization_id' => $source->organization_id,
                        'status' => 'approved',
                    ]
                );
            } elseif ($isNew) {
                // Scraped/dataset events wait for review; content_items dedupe
                // prevents re-creating the same pending event on later runs.
                Event::create([
                    'title' => str($item->title)->limit(250)->toString(),
                    'description' => $item->summary,
                    'starts_at' => $item->startsAt,
                    'ends_at' => $item->endsAt,
                    'location' => $item->location,
                    'url' => $item->url,
                    'organization_id' => $source->organization_id,
                    'source_id' => $source->id,
                    'status' => 'pending',
                ]);
            }
        }
    }
}
