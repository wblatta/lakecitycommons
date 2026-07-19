<?php

namespace App\Services\Fetchers;

use App\Models\Source;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class RssFetcher implements SourceFetcher
{
    public function fetch(Source $source): Collection
    {
        $body = Http::timeout(20)->get($source->url)->throw()->body();

        $xml = @simplexml_load_string($body, options: LIBXML_NOCDATA | LIBXML_NONET);
        if ($xml === false) {
            throw new \RuntimeException("Unparseable feed: {$source->url}");
        }

        $items = collect();

        if (isset($xml->channel->item)) { // RSS 2.0
            foreach ($xml->channel->item as $item) {
                $items->push(new FetchedItem(
                    title: trim((string) $item->title),
                    url: trim((string) ($item->link ?: $item->guid)) ?: null,
                    summary: str(strip_tags((string) $item->description))->squish()->limit(500)->toString() ?: null,
                    publishedAt: self::tryParse((string) $item->pubDate),
                ));
            }
        } elseif (isset($xml->entry)) { // Atom
            foreach ($xml->entry as $entry) {
                $href = null;
                foreach ($entry->link as $link) {
                    $href = (string) $link['href'];
                    if ((string) $link['rel'] === 'alternate' || (string) $link['rel'] === '') break;
                }
                $items->push(new FetchedItem(
                    title: trim((string) $entry->title),
                    url: $href ?: trim((string) $entry->id) ?: null,
                    summary: str(strip_tags((string) ($entry->summary ?: $entry->content)))->squish()->limit(500)->toString() ?: null,
                    publishedAt: self::tryParse((string) ($entry->published ?: $entry->updated)),
                ));
            }
        }

        return $items->filter(fn (FetchedItem $i) => $i->title !== '')->values();
    }

    private static function tryParse(string $value): ?Carbon
    {
        if ($value === '') return null;
        try { return Carbon::parse($value); } catch (\Throwable) { return null; }
    }
}
