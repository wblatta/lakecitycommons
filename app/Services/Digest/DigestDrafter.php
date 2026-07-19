<?php

namespace App\Services\Digest;

use Illuminate\Support\Collection;

interface DigestDrafter
{
    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\ContentItem> $items
     * @param \Illuminate\Support\Collection<int, \App\Models\Event> $events upcoming approved events
     * @return string markdown digest body
     */
    public function draft(Collection $items, Collection $events): string;
}
