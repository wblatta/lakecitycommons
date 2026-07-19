<?php

namespace App\Services\Fetchers;

use Carbon\CarbonInterface;

final class FetchedItem
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $url = null,
        public readonly ?string $summary = null,
        public readonly ?CarbonInterface $publishedAt = null,
        public readonly string $kind = 'news',            // news|event|notice
        public readonly ?CarbonInterface $startsAt = null,   // events only
        public readonly ?CarbonInterface $endsAt = null,
        public readonly ?string $location = null,
        public readonly ?string $externalUid = null,       // ICS UID
    ) {}

    public function contentHash(): string
    {
        return hash('sha256', mb_strtolower(trim($this->title)) . '|' . ($this->url ?? '') . '|' . ($this->startsAt?->toIso8601String() ?? ''));
    }
}
