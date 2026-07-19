<?php

namespace App\Services\Fetchers;

use App\Models\Source;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class DatasetFetcher implements SourceFetcher
{
    public function fetch(Source $source): Collection
    {
        $config = $source->selector_config ?? [];
        if (empty($config['title_field'])) {
            throw new \RuntimeException("Source #{$source->id} dataset config requires title_field");
        }

        $json = Http::timeout(20)->acceptJson()->get($source->url)->throw()->json();
        $records = empty($config['items_path']) ? $json : Arr::get($json, $config['items_path'], []);

        return collect($records)->map(function ($record) use ($config) {
            $title = trim((string) Arr::get($record, $config['title_field'], ''));
            if ($title === '') return null;

            $publishedAt = null;
            if (! empty($config['date_field']) && Arr::get($record, $config['date_field'])) {
                try { $publishedAt = Carbon::parse(Arr::get($record, $config['date_field'])); } catch (\Throwable) {}
            }

            $startsAt = null;
            if (! empty($config['starts_at_field']) && Arr::get($record, $config['starts_at_field'])) {
                try { $startsAt = Carbon::parse(Arr::get($record, $config['starts_at_field'])); } catch (\Throwable) {}
            }

            return new FetchedItem(
                title: str($title)->limit(250)->toString(),
                url: ! empty($config['url_field']) ? Arr::get($record, $config['url_field']) : null,
                summary: ! empty($config['summary_field']) ? str((string) Arr::get($record, $config['summary_field']))->squish()->limit(500)->toString() : null,
                publishedAt: $publishedAt,
                kind: $config['kind'] ?? 'notice',
                startsAt: $startsAt,
            );
        })->filter()->values();
    }
}
