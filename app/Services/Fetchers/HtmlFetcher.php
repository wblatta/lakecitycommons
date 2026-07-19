<?php

namespace App\Services\Fetchers;

use App\Models\Source;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class HtmlFetcher implements SourceFetcher
{
    public function fetch(Source $source): Collection
    {
        $config = $source->selector_config ?? [];
        if (empty($config['item_selector']) || empty($config['title_selector'])) {
            throw new \RuntimeException("Source #{$source->id} html config requires item_selector and title_selector");
        }

        $body = Http::timeout(20)->get($source->url)->throw()->body();
        $crawler = new Crawler($body, $source->url);

        [$linkSelector, $linkAttr] = str_contains($config['link_selector'] ?? 'a@href', '@')
            ? explode('@', $config['link_selector'] ?? 'a@href', 2)
            : [$config['link_selector'] ?? 'a', 'href'];

        return collect($crawler->filter($config['item_selector'])->each(function (Crawler $node) use ($config, $source, $linkSelector, $linkAttr) {
            $title = str($node->filter($config['title_selector'])->count() ? $node->filter($config['title_selector'])->text('') : '')->squish()->toString();
            if ($title === '') return null;

            $url = null;
            if ($node->filter($linkSelector)->count()) {
                $raw = $node->filter($linkSelector)->attr($linkAttr);
                $url = $raw ? (string) \Symfony\Component\DomCrawler\UriResolver::resolve($raw, $source->url) : null;
            }

            $summary = null;
            if (! empty($config['summary_selector']) && $node->filter($config['summary_selector'])->count()) {
                $summary = str($node->filter($config['summary_selector'])->text(''))->squish()->limit(500)->toString();
            }

            return new FetchedItem(title: $title, url: $url, summary: $summary, kind: $config['kind'] ?? 'news');
        }))->filter()->values();
    }
}
