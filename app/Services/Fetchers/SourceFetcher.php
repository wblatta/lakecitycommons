<?php

namespace App\Services\Fetchers;

use App\Models\Source;
use Illuminate\Support\Collection;

interface SourceFetcher
{
    /** @return \Illuminate\Support\Collection<int, FetchedItem> */
    public function fetch(Source $source): Collection;
}
