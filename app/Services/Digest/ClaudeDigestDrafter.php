<?php

namespace App\Services\Digest;

use Anthropic\Client;
use Illuminate\Support\Collection;

class ClaudeDigestDrafter implements DigestDrafter
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are the editor of Lake City Commons, a neighborhood news site for Lake City, Seattle.
Write this week's digest as markdown from the provided JSON of collected items and upcoming events.

Rules:
- Organize into sections, in this order, omitting empty ones: ## News, ## This Week's Events, ## Org Updates, ## City Notices
- Every factual claim must link to its source URL from the data. Never invent facts, dates, names, or events not present in the data.
- If an item seems uncertain, ambiguous, or contradictory, keep it but append the marker [VERIFY].
- Voice: warm, plainspoken, neighborly; short paragraphs; no hype. Write for people who live here.
- For events include day, date, time, and location.
- Start directly with the first section heading. No preamble, no title (the post title is added separately), no sign-off.
PROMPT;

    public function draft(Collection $items, Collection $events): string
    {
        $payload = json_encode([
            'week_of' => now()->toDateString(),
            'items' => $items->map(fn ($i) => [
                'kind' => $i->kind,
                'title' => $i->title,
                'url' => $i->url,
                'summary' => $i->summary,
                'published_at' => $i->published_at?->toDateString(),
                'source' => $i->source?->name,
            ])->values(),
            'upcoming_events' => $events->map(fn ($e) => [
                'title' => $e->title,
                'starts_at' => $e->starts_at->toDayDateTimeString(),
                'location' => $e->location,
                'url' => $e->url,
                'organization' => $e->organization?->name,
            ])->values(),
        ], JSON_UNESCAPED_SLASHES);

        $client = new Client(apiKey: (string) config('services.anthropic.key'));

        $message = $client->messages->create(
            model: (string) config('services.anthropic.model'),
            maxTokens: 8000,
            system: self::SYSTEM_PROMPT,
            messages: [['role' => 'user', 'content' => "Draft this week's digest from this data:\n\n" . $payload]],
        );

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        throw new \RuntimeException('Claude response contained no text block');
    }
}
