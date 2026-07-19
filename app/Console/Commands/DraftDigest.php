<?php

namespace App\Console\Commands;

use App\Models\ContentItem;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Services\Digest\DigestDrafter;
use App\Services\Digest\RawListDrafter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DraftDigest extends Command
{
    protected $signature = 'app:draft-digest';
    protected $description = 'Draft the weekly digest from fresh content items into the review queue';

    public const TITLE_PREFIX = 'Lake City This Week';

    public function handle(DigestDrafter $drafter): int
    {
        if (Post::whereIn('status', ['draft', 'review'])
            ->where('created_at', '>=', now()->subDays(6))
            ->where('title', 'like', self::TITLE_PREFIX . '%')
            ->exists()) {
            $this->info('A recent unpublished digest draft already exists; skipping.');
            return self::SUCCESS;
        }

        $items = ContentItem::unprocessed()->where('fetched_at', '>=', now()->subDays(8))->with('source')->get();
        $events = Event::approved()->whereBetween('starts_at', [now(), now()->addDays(10)])->with('organization')->orderBy('starts_at')->get();

        if ($items->isEmpty() && $events->isEmpty()) {
            $this->info('Nothing to report this week; skipping.');
            return self::SUCCESS;
        }

        try {
            $body = $drafter->draft($items, $events);
        } catch (\Throwable $e) {
            Log::warning('Digest drafter failed; using raw-list fallback', ['error' => $e->getMessage()]);
            $body = (new RawListDrafter)->draft($items, $events);
        }

        $author = User::where('role', 'admin')->orderBy('id')->firstOrFail();

        Post::create([
            'user_id' => $author->id,
            'title' => self::TITLE_PREFIX . ' — ' . now()->format('M j, Y'),
            'body' => $body,
            'status' => 'review',
        ]);

        ContentItem::whereIn('id', $items->pluck('id'))->update(['status' => 'in_digest']);

        $this->info("Digest drafted from {$items->count()} items and {$events->count()} events.");
        return self::SUCCESS;
    }
}
