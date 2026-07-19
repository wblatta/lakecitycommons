<?php

namespace App\Services\Digest;

use Illuminate\Support\Collection;

class RawListDrafter implements DigestDrafter
{
    public function draft(Collection $items, Collection $events): string
    {
        $sections = [
            'news' => '## News',
            'notice' => '## City Notices',
            'event' => '## Around the Neighborhood',
        ];

        $body = "*Automated fallback draft — the AI drafter was unavailable. Edit before publishing.*\n";

        foreach ($sections as $kind => $heading) {
            $group = $items->where('kind', $kind);
            if ($group->isEmpty()) continue;
            $body .= "\n{$heading}\n\n";
            foreach ($group as $item) {
                $line = $item->url ? "[{$item->title}]({$item->url})" : $item->title;
                $body .= "- {$line}" . ($item->summary ? " — {$item->summary}" : '') . "\n";
            }
        }

        if ($events->isNotEmpty()) {
            $body .= "\n## This Week's Events\n\n";
            foreach ($events as $event) {
                $body .= "- **{$event->title}** — {$event->starts_at->format('D M j, g:i A')}"
                    . ($event->location ? " at {$event->location}" : '') . "\n";
            }
        }

        return $body;
    }
}
