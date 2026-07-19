<?php

namespace App\Services\Fetchers;

use App\Models\Source;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class IcsFetcher implements SourceFetcher
{
    public function fetch(Source $source): Collection
    {
        $body = Http::timeout(20)->get($source->url)->throw()->body();

        // Unfold: CRLF (or LF) followed by space/tab continues the previous line
        $unfolded = preg_replace("/\r?\n([ \t])/", '$1', $body);
        $lines = preg_split("/\r?\n/", $unfolded);

        $items = collect();
        $event = null;

        foreach ($lines as $line) {
            if (trim($line) === 'BEGIN:VEVENT') { $event = []; continue; }
            if (trim($line) === 'END:VEVENT') {
                if ($event !== null && ($event['SUMMARY'] ?? '') !== '' && isset($event['DTSTART'])) {
                    $items->push(new FetchedItem(
                        title: $event['SUMMARY'],
                        url: $event['URL'] ?? null,
                        summary: isset($event['DESCRIPTION']) ? str($event['DESCRIPTION'])->squish()->limit(500)->toString() : null,
                        kind: 'event',
                        startsAt: $event['DTSTART'],
                        endsAt: $event['DTEND'] ?? null,
                        location: $event['LOCATION'] ?? null,
                        externalUid: $event['UID'] ?? null,
                    ));
                }
                $event = null;
                continue;
            }
            if ($event === null || ! str_contains($line, ':')) continue;

            [$rawName, $value] = explode(':', $line, 2);
            $name = strtoupper(explode(';', $rawName, 2)[0]);
            $params = strtoupper($rawName);

            if (in_array($name, ['DTSTART', 'DTEND'], true)) {
                $parsed = self::parseIcsDate(trim($value), $params);
                if ($parsed) { $event[$name] = $parsed; }
            } elseif (in_array($name, ['SUMMARY', 'LOCATION', 'DESCRIPTION', 'URL', 'UID'], true)) {
                $event[$name] = self::unescape(trim($value));
            }
        }

        return $items;
    }

    private static function parseIcsDate(string $value, string $params): ?Carbon
    {
        try {
            if (str_contains($params, 'VALUE=DATE') || preg_match('/^\d{8}$/', $value)) {
                return Carbon::createFromFormat('Ymd', substr($value, 0, 8), config('app.timezone'))->startOfDay();
            }
            if (str_ends_with($value, 'Z')) {
                return Carbon::createFromFormat('Ymd\THis\Z', $value, 'UTC');
            }
            // Local or TZID-qualified times: parse in the TZID when present, else app tz
            $tz = config('app.timezone');
            if (preg_match('/TZID=([^;:]+)/', $params, $m)) {
                $tz = trim($m[1]);
            }
            return Carbon::createFromFormat('Ymd\THis', $value, $tz);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function unescape(string $value): string
    {
        return str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $value);
    }
}
