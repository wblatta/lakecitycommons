# Lake City Commons — Content Pipeline + Visual Identity Implementation Plan (Plan 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automate the weekly content cycle — fetch from org/city sources, dedupe, draft the digest with the Claude API into the admin review queue, and publish with an email-ready export for manual sending — plus give the site its visual identity (logo, favicon, OG image).

**Architecture:** A `sources` registry feeds a weekly `app:fetch-sources` console command (Laravel scheduler, Friday 22:00 local) that normalizes items into `content_items` with hash-based dedupe; ICS events flow straight to the public calendar, scraped events wait in the review queue. A Saturday 06:00 `app:draft-digest` command sends the week's items to the Claude API (official `anthropic-ai/sdk` PHP SDK behind a `DigestDrafter` interface) and stores the draft as a `review`-status Post. An explicit admin Publish action sets the post live; an **email-ready view** renders the digest as clean copy-pasteable HTML for the owner to send manually from their own mail client (no newsletter service — deliberately deferred until list size justifies one). Visual identity: three controller-designed SVG logo candidates → user picks → applied to nav, favicon, and OG image.

**Tech Stack:** Laravel 12 (PHP 8.3+), Blade + Tailwind, MySQL prod / SQLite-memory tests. New composer packages (the ONLY ones permitted): `anthropic-ai/sdk`, `symfony/dom-crawler`, `symfony/css-selector`.

## Global Constraints

- New composer packages limited to exactly: `anthropic-ai/sdk`, `symfony/dom-crawler`, `symfony/css-selector`. No npm additions.
- Claude model: `claude-sonnet-5` (per approved spec), via the official PHP SDK — camelCase named args (`maxTokens:`), no `temperature`/sampling params, no `thinking` config (adaptive is the default). `max_tokens` 8000, non-streaming.
- **No test may hit a live external service.** Fetchers use `Http::fake()`; Claude tests swap the `DigestDrafter` binding — `ClaudeDigestDrafter` itself is deploy-verified, not network-tested.
- **No newsletter service.** The owner sends the digest manually from their own mail client using the email-ready view. `posts.newsletter_sent_at` stays in the schema (unused for now) for a future automation phase.
- The digest NEVER auto-publishes: drafts are created with `status='review'`.
- All commands idempotent — safe to re-run (dedupe via unique `(source_id, content_hash)`; digest command skips if a review/draft digest post already exists for the period).
- DreamHost shared hosting: scheduler via a single crontab entry running `php artisan schedule:run`; `database` cache/queue; no Redis/websockets. All scheduled times are `America/Los_Angeles` (the app timezone).
- Per-source failures are isolated: one bad source never aborts a fetch run. `consecutive_failures >= 2` shows an admin banner and emails admins once per failure streak.
- Follow existing patterns: inline `$request->validate()`, `AdminAuditLog::create()` on admin create/delete/moderation, `<x-app-layout>` admin views, existing Tailwind palette, `FEATURE_COMMUNITY` untouched.
- Branch: `content-pipeline` off `main`.
- Spec: `docs/superpowers/specs/2026-07-19-lake-city-commons-design.md` (sections: Content pipeline, Admin additions).

## Execution ordering note

Tasks 1–9 (pipeline) are strictly sequential. Tasks L1–L2 (visual identity) are independent of the pipeline: L1 runs first overall (user needs time to pick a logo), L2 runs whenever the user has picked — possibly interleaved between pipeline tasks. L1 and L2 are **controller-executed design tasks** (they require visual design judgment and local image tooling), not subagent dispatches.

---

### Task L1: Logo candidates (controller-executed)

**Files:**
- Create: `public/branding/option-a.svg`, `option-b.svg`, `option-c.svg`
- Create: `public/branding/preview.html` (static, no build step)

The controller designs three SVG logo candidates for "Lake City Commons" blending community/commons and evergreen/nature motifs, using the existing palette (forest green on cream; check `tailwind.config.js` for exact hex values). Each candidate: a compact mark (square-ish, legible at 32px) plus a horizontal lockup with the wordmark in Fraunces. `preview.html` shows all three at nav size, favicon size (32px), and large, on both cream and white backgrounds. Commit with `feat: add logo candidates for review`. Present the three options to the user (screenshot or served preview) and record the pick. **USER GATE: do not start L2 until the user picks.**

### Task L2: Apply visual identity (controller-executed, after user pick)

**Files:**
- Create: `public/favicon.svg` (chosen mark), replace `public/favicon.ico`
- Create: `public/images/og-default.png` (1200×630, generated locally from the chosen lockup SVG via `rsvg-convert` or ImageMagick)
- Modify: `resources/views/layouts/public.blade.php` — replace the text brand with the chosen lockup SVG (inline, `h-8`), add `<link rel="icon" href="/favicon.svg" type="image/svg+xml">` and a default `og:image` meta (`asset('images/og-default.png')`) emitted when a page doesn't set its own
- Modify: `resources/views/layouts/app.blade.php` — same favicon links; swap the placeholder SVG next to the brand for the chosen mark
- Delete: `public/branding/` (candidates + preview no longer needed)

Verify: `php artisan test` green (no behavior change expected; update any test asserting the old brand markup), pages render with logo in browser. Commit `feat: apply Lake City Commons visual identity`.

---

### Task 1: Composer dependencies + service config

**Files:**
- Modify: `composer.json`/`composer.lock` (via `composer require`)
- Modify: `config/services.php`, `.env.example`
- Test: `tests/Feature/ConfigCheckTest.php` (extend existing file)

**Interfaces:**
- Produces: `config('services.anthropic.key')`, `config('services.anthropic.model')` (default `claude-sonnet-5`). Packages available to later tasks. (The existing `services.buttondown.signup_url` entry from Plan 1 stays as-is — unset in production, so the footer signup link simply doesn't render.)

- [ ] **Step 1: Write the failing test** — add to `tests/Feature/ConfigCheckTest.php` (match the file's existing style):

```php
public function test_pipeline_service_config_is_defined(): void
{
    config(['services.anthropic.model' => null]);
    $this->assertNull(config('services.anthropic.model'));

    // Reload defaults from the config file shape
    $services = require config_path('services.php');
    $this->assertArrayHasKey('anthropic', $services);
    $this->assertArrayHasKey('key', $services['anthropic']);
    $this->assertSame('claude-sonnet-5', $services['anthropic']['model'] ?? env('ANTHROPIC_MODEL', 'claude-sonnet-5'));
}
```

- [ ] **Step 2: Run test to verify it fails** — `php artisan test --filter=ConfigCheckTest` → FAIL (keys missing).

- [ ] **Step 3: Implement**

```bash
composer require anthropic-ai/sdk symfony/dom-crawler symfony/css-selector --no-interaction
```

`config/services.php` — add alongside the existing `buttondown` entry:

```php
'anthropic' => [
    'key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
],
```

`.env.example` — add:

```
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-5
```

- [ ] **Step 4: Run tests** — filter PASS, then full suite green (110 + 1).
- [ ] **Step 5: Commit** — `git add -A && git commit -m "chore: add anthropic sdk and dom-crawler; pipeline service config"`

---

### Task 2: Data layer — sources, content_items, events pipeline columns

**Files:**
- Create: `database/migrations/2026_07_20_000001_create_sources_table.php`
- Create: `database/migrations/2026_07_20_000002_create_content_items_table.php`
- Create: `database/migrations/2026_07_20_000003_add_pipeline_columns_to_events_table.php`
- Create: `app/Models/Source.php`, `app/Models/ContentItem.php`
- Create: `database/factories/SourceFactory.php`, `database/factories/ContentItemFactory.php`
- Modify: `app/Models/Event.php` (fillable + source relation)
- Test: `tests/Unit/PipelineDataTest.php`

**Interfaces:**
- Produces: `Source` — fillable `name, url, type, selector_config, organization_id, active, last_fetched_at, last_succeeded_at, consecutive_failures, failure_notified_at`; `selector_config` array cast; datetime casts; `Source::TYPES = ['rss','ics','html','dataset']`; `scopeActive`; `contentItems()` hasMany. `ContentItem` — fillable `source_id, url, title, summary, content_hash, kind, published_at, fetched_at, status`; kinds `news|event|notice`; statuses `new|in_digest|ignored`; `scopeUnprocessed` (status new — deliberately NOT named `fresh`, which would collide with Eloquent's built-in `Model::fresh()`); unique `(source_id, content_hash)`. `Event` gains fillable `source_id, external_uid`; unique `(source_id, external_uid)` (NULLs don't collide in MySQL/SQLite).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\ContentItem;
use App\Models\Event;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_casts_and_scope(): void
    {
        $source = Source::factory()->create(['selector_config' => ['item_selector' => '.post'], 'active' => true]);
        Source::factory()->create(['active' => false]);

        $this->assertSame('.post', $source->fresh()->selector_config['item_selector']);
        $this->assertEquals(1, Source::active()->count());
    }

    public function test_content_item_dedupe_unique_constraint(): void
    {
        $source = Source::factory()->create();
        ContentItem::factory()->create(['source_id' => $source->id, 'content_hash' => str_repeat('a', 64)]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ContentItem::factory()->create(['source_id' => $source->id, 'content_hash' => str_repeat('a', 64)]);
    }

    public function test_event_upsert_by_source_and_external_uid(): void
    {
        $source = Source::factory()->create(['type' => 'ics']);

        Event::updateOrCreate(
            ['source_id' => $source->id, 'external_uid' => 'uid-1@cal'],
            ['title' => 'Original', 'starts_at' => now()->addDay(), 'status' => 'approved']
        );
        Event::updateOrCreate(
            ['source_id' => $source->id, 'external_uid' => 'uid-1@cal'],
            ['title' => 'Updated', 'starts_at' => now()->addDays(2), 'status' => 'approved']
        );

        $this->assertEquals(1, Event::count());
        $this->assertSame('Updated', Event::first()->title);
    }
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter=PipelineDataTest` → FAIL.

- [ ] **Step 3: Implement**

`create_sources_table` migration:

```php
Schema::create('sources', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('url', 2048);
    $table->string('type'); // rss|ics|html|dataset
    $table->json('selector_config')->nullable();
    $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
    $table->boolean('active')->default(true);
    $table->timestamp('last_fetched_at')->nullable();
    $table->timestamp('last_succeeded_at')->nullable();
    $table->unsignedInteger('consecutive_failures')->default(0);
    $table->timestamp('failure_notified_at')->nullable();
    $table->timestamps();
});
```

`create_content_items_table` migration:

```php
Schema::create('content_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('source_id')->constrained()->cascadeOnDelete();
    $table->string('url', 2048)->nullable();
    $table->string('title');
    $table->text('summary')->nullable();
    $table->string('content_hash', 64);
    $table->string('kind'); // news|event|notice
    $table->timestamp('published_at')->nullable();
    $table->timestamp('fetched_at');
    $table->string('status')->default('new'); // new|in_digest|ignored
    $table->timestamps();
    $table->unique(['source_id', 'content_hash']);
    $table->index(['status', 'fetched_at']);
});
```

`add_pipeline_columns_to_events_table` migration:

```php
Schema::table('events', function (Blueprint $table) {
    $table->foreignId('source_id')->nullable()->after('organization_id')->constrained('sources')->nullOnDelete();
    $table->string('external_uid')->nullable()->after('submission_id');
    $table->unique(['source_id', 'external_uid']);
});
```

(down(): drop unique, drop foreign, drop columns — in that order.)

`app/Models/Source.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    use HasFactory;

    public const TYPES = ['rss', 'ics', 'html', 'dataset'];

    protected $fillable = [
        'name', 'url', 'type', 'selector_config', 'organization_id', 'active',
        'last_fetched_at', 'last_succeeded_at', 'consecutive_failures', 'failure_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'selector_config' => 'array',
            'active' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_succeeded_at' => 'datetime',
            'failure_notified_at' => 'datetime',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function contentItems()
    {
        return $this->hasMany(ContentItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
```

`app/Models/ContentItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id', 'url', 'title', 'summary', 'content_hash',
        'kind', 'published_at', 'fetched_at', 'status',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'fetched_at' => 'datetime'];
    }

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('status', 'new');
    }
}
```

Factories:

```php
// SourceFactory
public function definition(): array
{
    return [
        'name' => fake()->company() . ' Feed',
        'url' => fake()->url(),
        'type' => 'rss',
        'active' => true,
    ];
}
```

```php
// ContentItemFactory
public function definition(): array
{
    return [
        'source_id' => \App\Models\Source::factory(),
        'url' => fake()->url(),
        'title' => fake()->sentence(6),
        'summary' => fake()->paragraph(),
        'content_hash' => hash('sha256', fake()->unique()->sentence()),
        'kind' => 'news',
        'published_at' => now()->subDays(2),
        'fetched_at' => now(),
        'status' => 'new',
    ];
}
```

`app/Models/Event.php` — add `'source_id', 'external_uid'` to `$fillable`, and:

```php
public function source()
{
    return $this->belongsTo(Source::class);
}
```

- [ ] **Step 4: Run tests** — filter PASS, full suite green.
- [ ] **Step 5: Commit** — `feat: sources and content_items data layer with event upsert columns`

---

### Task 3: Admin sources CRUD with health display

**Files:**
- Create: `app/Http/Controllers/Admin/SourceController.php`
- Create: `resources/views/admin/sources/index.blade.php`, `create.blade.php`, `edit.blade.php`, `_form.blade.php`
- Modify: `routes/web.php` (admin group), `resources/views/layouts/app.blade.php` (admin nav link "Sources" beside Organizations)
- Test: `tests/Feature/Admin/SourceAdminTest.php`

**Interfaces:**
- Consumes: `Source` (Task 2), admin patterns (PostController/OrganizationController).
- Produces: routes `admin.sources.*` (resource except show). `selector_config` edited as a JSON textarea (nullable; validated as valid JSON when present). Index shows per-source health: last success, consecutive failures (red badge when >= 2).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'admin']);
    }

    public function test_admin_can_create_source_with_selector_config(): void
    {
        $this->actingAs($this->admin())->post('/admin/sources', [
            'name' => 'LCNA Blog',
            'url' => 'https://example.org/feed.xml',
            'type' => 'rss',
            'selector_config' => '',
            'active' => 1,
        ])->assertRedirect(route('admin.sources.index'));

        $this->assertDatabaseHas('sources', ['name' => 'LCNA Blog', 'type' => 'rss']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'source_create']);
    }

    public function test_invalid_json_selector_config_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/sources', [
            'name' => 'Broken', 'url' => 'https://example.org', 'type' => 'html',
            'selector_config' => '{not json',
        ])->assertSessionHasErrors('selector_config');
    }

    public function test_invalid_type_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/sources', [
            'name' => 'X', 'url' => 'https://example.org', 'type' => 'carrier-pigeon',
        ])->assertSessionHasErrors('type');
    }

    public function test_index_shows_failure_badge(): void
    {
        Source::factory()->create(['name' => 'Flaky Feed', 'consecutive_failures' => 3]);

        $this->actingAs($this->admin())->get('/admin/sources')
            ->assertOk()->assertSee('Flaky Feed')->assertSee('3 failures');
    }

    public function test_admin_can_delete_source(): void
    {
        $source = Source::factory()->create();

        $this->actingAs($this->admin())->delete("/admin/sources/{$source->id}");
        $this->assertDatabaseMissing('sources', ['id' => $source->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'source_delete']);
    }

    public function test_non_admin_blocked(): void
    {
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);
        $this->actingAs($member)->get('/admin/sources')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Implement** — `SourceController` mirrors `OrganizationController`: `index` (orderBy name, paginate 30), `create`/`store`, `edit`/`update`, `destroy`; audit log on create/delete. Validation:

```php
private function validated(Request $request): array
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'url' => 'required|url:http,https|max:2048',
        'type' => 'required|in:' . implode(',', Source::TYPES),
        'selector_config' => 'nullable|string|json',
        'organization_id' => 'nullable|exists:organizations,id',
        'active' => 'boolean',
    ]);

    $data['selector_config'] = filled($data['selector_config'] ?? null)
        ? json_decode($data['selector_config'], true)
        : null;
    $data['active'] = $request->boolean('active');

    return $data;
}
```

Views follow the organizations views' `<x-app-layout>` convention. `_form.blade.php`: name, url, type select (from `Source::TYPES`), organization select (nullable, from `Organization::orderBy('name')`), selector_config `<textarea>` (pre-filled with `json_encode($source->selector_config, JSON_PRETTY_PRINT)` when editing), active checkbox. Index table columns: Name, Type, Org, Last success (`diffForHumans()` or `—`), Health (`{{ $source->consecutive_failures }} failures` in a red badge when `>= 2`, otherwise green `ok`), actions. Route inside the admin group:

```php
Route::resource('sources', \App\Http\Controllers\Admin\SourceController::class)->except(['show']);
```

Admin nav: add a `Sources` link beside `Organizations` in `layouts/app.blade.php` (same markup style, `admin.sources.*` active-state).

- [ ] **Step 4: Run tests** — filter PASS, full suite green.
- [ ] **Step 5: Commit** — `feat: admin sources registry with health display`

---

### Task 4: Fetcher framework + RSS and ICS fetchers

**Files:**
- Create: `app/Services/Fetchers/FetchedItem.php` (DTO), `SourceFetcher.php` (interface), `RssFetcher.php`, `IcsFetcher.php`
- Create: `tests/fixtures/feed-rss.xml`, `tests/fixtures/feed-atom.xml`, `tests/fixtures/calendar.ics`
- Test: `tests/Feature/Fetchers/RssFetcherTest.php`, `tests/Feature/Fetchers/IcsFetcherTest.php`

**Interfaces:**
- Produces:

```php
interface SourceFetcher
{
    /** @return \Illuminate\Support\Collection<int, FetchedItem> */
    public function fetch(Source $source): \Illuminate\Support\Collection;
}
```

```php
final class FetchedItem
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $url = null,
        public readonly ?string $summary = null,
        public readonly ?\Carbon\CarbonInterface $publishedAt = null,
        public readonly string $kind = 'news',            // news|event|notice
        public readonly ?\Carbon\CarbonInterface $startsAt = null,   // events only
        public readonly ?\Carbon\CarbonInterface $endsAt = null,
        public readonly ?string $location = null,
        public readonly ?string $externalUid = null,       // ICS UID
    ) {}

    public function contentHash(): string
    {
        return hash('sha256', mb_strtolower(trim($this->title)) . '|' . ($this->url ?? '') . '|' . ($this->startsAt?->toIso8601String() ?? ''));
    }
}
```

All fetchers use `Http::timeout(20)->get($source->url)->throw()->body()` so tests fake with `Http::fake`. Fetch errors throw — the command (Task 6) isolates them.

- [ ] **Step 1: Write the failing tests**

`tests/fixtures/feed-rss.xml` (abridged but valid RSS 2.0 — two items with title/link/description/pubDate/guid). `tests/fixtures/feed-atom.xml` (Atom — two entries with title/link href/summary/updated/id). `tests/fixtures/calendar.ics`:

```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
BEGIN:VEVENT
UID:evt-100@example.org
DTSTAMP:20260710T000000Z
DTSTART:20260801T020000Z
DTEND:20260801T040000Z
SUMMARY:Summer Concert in the
  Park
LOCATION:Lake City Community Center
URL:https://example.org/concert
END:VEVENT
BEGIN:VEVENT
UID:evt-101@example.org
DTSTAMP:20260710T000000Z
DTSTART;VALUE=DATE:20260815
SUMMARY:All-Day Cleanup
END:VEVENT
END:VCALENDAR
```

(Note the folded SUMMARY line — continuation lines start with a single space per RFC 5545; the parser must unfold.)

`RssFetcherTest`:

```php
public function test_parses_rss_items(): void
{
    Http::fake(['example.org/*' => Http::response(file_get_contents(base_path('tests/fixtures/feed-rss.xml')))]);
    $source = Source::factory()->create(['type' => 'rss', 'url' => 'https://example.org/feed.xml']);

    $items = (new RssFetcher)->fetch($source);

    $this->assertCount(2, $items);
    $this->assertSame('news', $items[0]->kind);
    $this->assertNotEmpty($items[0]->title);
    $this->assertNotNull($items[0]->url);
}

public function test_parses_atom_entries(): void
{
    Http::fake(['example.org/*' => Http::response(file_get_contents(base_path('tests/fixtures/feed-atom.xml')))]);
    $source = Source::factory()->create(['type' => 'rss', 'url' => 'https://example.org/atom.xml']);

    $this->assertCount(2, (new RssFetcher)->fetch($source));
}

public function test_http_failure_throws(): void
{
    Http::fake(['example.org/*' => Http::response('', 500)]);
    $source = Source::factory()->create(['type' => 'rss', 'url' => 'https://example.org/feed.xml']);

    $this->expectException(\Illuminate\Http\Client\RequestException::class);
    (new RssFetcher)->fetch($source);
}
```

`IcsFetcherTest`:

```php
public function test_parses_vevents_with_folded_lines_and_all_day(): void
{
    Http::fake(['example.org/*' => Http::response(file_get_contents(base_path('tests/fixtures/calendar.ics')))]);
    $source = Source::factory()->create(['type' => 'ics', 'url' => 'https://example.org/cal.ics']);

    $items = (new IcsFetcher)->fetch($source);

    $this->assertCount(2, $items);
    $this->assertSame('event', $items[0]->kind);
    $this->assertSame('Summer Concert in the Park', $items[0]->title);
    $this->assertSame('evt-100@example.org', $items[0]->externalUid);
    $this->assertSame('2026-08-01 02:00:00', $items[0]->startsAt->utc()->format('Y-m-d H:i:s'));
    $this->assertSame('Lake City Community Center', $items[0]->location);
    $this->assertSame('All-Day Cleanup', $items[1]->title);
    $this->assertNotNull($items[1]->startsAt); // date-only DTSTART parsed as start of day
}
```

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Implement**

`RssFetcher` — SimpleXML, tolerant of RSS 2.0 and Atom:

```php
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
```

`IcsFetcher` — hand-rolled RFC 5545 parsing (unfold, walk VEVENTs):

```php
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
        $unfolded = preg_replace("/\r?\n[ \t]/", '', $body);
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
                $event[$name] = self::parseIcsDate(trim($value), $params);
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
```

(Note: `parseIcsDate` may return null; the `isset($event['DTSTART'])` guard above then drops the event — malformed dates never produce bad rows. Also note the DTSTART key is only considered set when parsing succeeded: assign via `$parsed = self::parseIcsDate(...); if ($parsed) { $event[$name] = $parsed; }` — implement it that way.)

- [ ] **Step 4: Run tests** — both filters PASS, full suite green.
- [ ] **Step 5: Commit** — `feat: fetcher framework with RSS/Atom and ICS fetchers`

---

### Task 5: HTML and dataset fetchers

**Files:**
- Create: `app/Services/Fetchers/HtmlFetcher.php`, `DatasetFetcher.php`
- Create: `tests/fixtures/orgpage.html`, `tests/fixtures/permits.json`
- Test: `tests/Feature/Fetchers/HtmlFetcherTest.php`, `tests/Feature/Fetchers/DatasetFetcherTest.php`

**Interfaces:**
- Consumes: `SourceFetcher`/`FetchedItem` (Task 4), `symfony/dom-crawler` (Task 1).
- Produces: `HtmlFetcher` reads `selector_config`: `item_selector` (required), `title_selector` (required), `link_selector` (optional; `a` default; `@href` attr default, `selector@attr` syntax supported), `summary_selector` (optional), `kind` (optional, default `news`). Relative link URLs resolved against the source URL. `DatasetFetcher` reads `selector_config`: `items_path` (optional dot-path into the JSON; default root array), `title_field` (required), `url_field`, `date_field`, `summary_field`, `kind` (default `notice`). Missing required config throws `RuntimeException`.

- [ ] **Step 1: Write the failing tests**

`tests/fixtures/orgpage.html`:

```html
<html><body>
  <div class="news">
    <article class="post">
      <h3 class="title">Fall Festival Announced</h3>
      <a class="more" href="/news/fall-festival">Read more</a>
      <p class="excerpt">Join us in October for the annual festival.</p>
    </article>
    <article class="post">
      <h3 class="title">Board Meeting Minutes</h3>
      <a class="more" href="https://example.org/news/minutes">Read more</a>
      <p class="excerpt">Minutes from the July board meeting.</p>
    </article>
  </div>
</body></html>
```

`tests/fixtures/permits.json`:

```json
{"results": [
  {"description": "New mixed-use building", "permit_url": "https://permits.example/1", "issued": "2026-07-15", "address": "12345 Lake City Way NE"},
  {"description": "Deck addition", "permit_url": "https://permits.example/2", "issued": "2026-07-16", "address": "2200 NE 125th St"}
]}
```

`HtmlFetcherTest`:

```php
public function test_scrapes_items_with_selectors_and_resolves_relative_urls(): void
{
    Http::fake(['example.org/*' => Http::response(file_get_contents(base_path('tests/fixtures/orgpage.html')))]);
    $source = Source::factory()->create([
        'type' => 'html',
        'url' => 'https://example.org/news',
        'selector_config' => [
            'item_selector' => 'article.post',
            'title_selector' => '.title',
            'link_selector' => 'a.more@href',
            'summary_selector' => '.excerpt',
        ],
    ]);

    $items = (new HtmlFetcher)->fetch($source);

    $this->assertCount(2, $items);
    $this->assertSame('Fall Festival Announced', $items[0]->title);
    $this->assertSame('https://example.org/news/fall-festival', $items[0]->url);
    $this->assertSame('https://example.org/news/minutes', $items[1]->url);
}

public function test_missing_required_config_throws(): void
{
    $source = Source::factory()->create(['type' => 'html', 'selector_config' => null]);
    $this->expectException(\RuntimeException::class);
    (new HtmlFetcher)->fetch($source);
}
```

`DatasetFetcherTest`:

```php
public function test_maps_json_records(): void
{
    Http::fake(['data.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/permits.json')))]);
    $source = Source::factory()->create([
        'type' => 'dataset',
        'url' => 'https://data.example/permits.json',
        'selector_config' => [
            'items_path' => 'results',
            'title_field' => 'description',
            'url_field' => 'permit_url',
            'date_field' => 'issued',
            'summary_field' => 'address',
        ],
    ]);

    $items = (new DatasetFetcher)->fetch($source);

    $this->assertCount(2, $items);
    $this->assertSame('notice', $items[0]->kind);
    $this->assertSame('New mixed-use building', $items[0]->title);
    $this->assertSame('2026-07-15', $items[0]->publishedAt->format('Y-m-d'));
}
```

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Implement**

`HtmlFetcher`:

```php
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
```

(If `Symfony\Component\DomCrawler\UriResolver` is unavailable in the installed version, use `Crawler->filter(...)->link()->getUri()` via `new Crawler($body, $source->url)` — the Crawler was constructed with the base URI for exactly this. Verify which API exists in the installed package and use it; the test pins the behavior.)

`DatasetFetcher`:

```php
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

            return new FetchedItem(
                title: str($title)->limit(250)->toString(),
                url: ! empty($config['url_field']) ? Arr::get($record, $config['url_field']) : null,
                summary: ! empty($config['summary_field']) ? str((string) Arr::get($record, $config['summary_field']))->squish()->limit(500)->toString() : null,
                publishedAt: $publishedAt,
                kind: $config['kind'] ?? 'notice',
            );
        })->filter()->values();
    }
}
```

- [ ] **Step 4: Run tests** — both filters PASS, full suite green.
- [ ] **Step 5: Commit** — `feat: html and dataset fetchers`

---

### Task 6: FetchSources command — dedupe, event upserts, health counters, schedule

**Files:**
- Create: `app/Console/Commands/FetchSources.php`
- Modify: `routes/console.php` (schedule entry)
- Test: `tests/Feature/FetchSourcesCommandTest.php`

**Interfaces:**
- Consumes: all fetchers (Tasks 4–5), `Source`/`ContentItem`/`Event` (Task 2).
- Produces: `php artisan app:fetch-sources`. Behavior per active source: resolve fetcher by `type`; on success store new `ContentItem`s (dedupe by `(source_id, content_hash)`, skip existing silently), upsert ICS events as `status=approved` keyed by `(source_id, external_uid)`, create html/dataset-sourced events as `status=pending` (deduped via content_items so they aren't re-created weekly), update `last_fetched_at`/`last_succeeded_at`, reset `consecutive_failures` to 0 and `failure_notified_at` to null; on failure log warning, set `last_fetched_at`, increment `consecutive_failures`, continue to the next source. Scheduled Fridays 22:00 (app tz).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\Event;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchSourcesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetches_stores_and_dedupes_items(): void
    {
        Http::fake(['rss.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/feed-rss.xml')))]);
        $source = Source::factory()->create(['type' => 'rss', 'url' => 'https://rss.example/feed.xml']);

        $this->artisan('app:fetch-sources')->assertSuccessful();
        $this->assertEquals(2, ContentItem::count());

        // Second run: same items, nothing duplicated
        $this->artisan('app:fetch-sources')->assertSuccessful();
        $this->assertEquals(2, ContentItem::count());

        $source->refresh();
        $this->assertNotNull($source->last_succeeded_at);
        $this->assertEquals(0, $source->consecutive_failures);
    }

    public function test_ics_events_upsert_approved(): void
    {
        Http::fake(['cal.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/calendar.ics')))]);
        Source::factory()->create(['type' => 'ics', 'url' => 'https://cal.example/cal.ics']);

        $this->artisan('app:fetch-sources')->assertSuccessful();
        $this->artisan('app:fetch-sources')->assertSuccessful();

        $this->assertEquals(2, Event::count());
        $this->assertEquals(2, Event::where('status', 'approved')->count());
    }

    public function test_failure_isolated_and_counted(): void
    {
        Http::fake([
            'down.example/*' => Http::response('', 500),
            'rss.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/feed-rss.xml'))),
        ]);
        $bad = Source::factory()->create(['type' => 'rss', 'url' => 'https://down.example/feed.xml']);
        Source::factory()->create(['type' => 'rss', 'url' => 'https://rss.example/feed.xml']);

        $this->artisan('app:fetch-sources')->assertSuccessful();

        $this->assertEquals(1, $bad->fresh()->consecutive_failures);
        $this->assertEquals(2, ContentItem::count()); // good source still processed
    }

    public function test_success_resets_failure_streak(): void
    {
        Http::fake(['rss.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/feed-rss.xml')))]);
        $source = Source::factory()->create([
            'type' => 'rss', 'url' => 'https://rss.example/feed.xml',
            'consecutive_failures' => 3, 'failure_notified_at' => now(),
        ]);

        $this->artisan('app:fetch-sources')->assertSuccessful();

        $source->refresh();
        $this->assertEquals(0, $source->consecutive_failures);
        $this->assertNull($source->failure_notified_at);
    }

    public function test_inactive_sources_skipped(): void
    {
        Http::fake();
        Source::factory()->create(['active' => false, 'url' => 'https://never.example/feed.xml']);

        $this->artisan('app:fetch-sources')->assertSuccessful();
        Http::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Console\Commands;

use App\Models\ContentItem;
use App\Models\Event;
use App\Models\Source;
use App\Services\Fetchers\DatasetFetcher;
use App\Services\Fetchers\FetchedItem;
use App\Services\Fetchers\HtmlFetcher;
use App\Services\Fetchers\IcsFetcher;
use App\Services\Fetchers\RssFetcher;
use App\Services\Fetchers\SourceFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchSources extends Command
{
    protected $signature = 'app:fetch-sources';
    protected $description = 'Fetch all active content sources, dedupe into content_items, and sync events';

    public function handle(): int
    {
        foreach (Source::active()->get() as $source) {
            $source->forceFill(['last_fetched_at' => now()]);

            try {
                $items = $this->fetcherFor($source)->fetch($source);
                $this->store($source, $items);
                $source->forceFill([
                    'last_succeeded_at' => now(),
                    'consecutive_failures' => 0,
                    'failure_notified_at' => null,
                ]);
                $this->info("{$source->name}: {$items->count()} items");
            } catch (\Throwable $e) {
                $source->consecutive_failures++;
                Log::warning('Source fetch failed', ['source_id' => $source->id, 'error' => $e->getMessage()]);
                $this->warn("{$source->name}: FAILED ({$e->getMessage()})");
            }

            $source->save();
        }

        return self::SUCCESS;
    }

    private function fetcherFor(Source $source): SourceFetcher
    {
        return match ($source->type) {
            'rss' => new RssFetcher,
            'ics' => new IcsFetcher,
            'html' => new HtmlFetcher,
            'dataset' => new DatasetFetcher,
            default => throw new \RuntimeException("Unknown source type: {$source->type}"),
        };
    }

    private function store(Source $source, $items): void
    {
        foreach ($items as $item) {
            /** @var FetchedItem $item */
            $isNew = ContentItem::firstOrCreate(
                ['source_id' => $source->id, 'content_hash' => $item->contentHash()],
                [
                    'url' => $item->url,
                    'title' => str($item->title)->limit(250)->toString(),
                    'summary' => $item->summary,
                    'kind' => $item->kind,
                    'published_at' => $item->publishedAt ?? $item->startsAt,
                    'fetched_at' => now(),
                ]
            )->wasRecentlyCreated;

            if ($item->kind !== 'event' || ! $item->startsAt) {
                continue;
            }

            if ($source->type === 'ics' && $item->externalUid) {
                Event::updateOrCreate(
                    ['source_id' => $source->id, 'external_uid' => $item->externalUid],
                    [
                        'title' => str($item->title)->limit(250)->toString(),
                        'description' => $item->summary,
                        'starts_at' => $item->startsAt,
                        'ends_at' => $item->endsAt,
                        'location' => $item->location,
                        'url' => $item->url,
                        'organization_id' => $source->organization_id,
                        'status' => 'approved',
                    ]
                );
            } elseif ($isNew) {
                // Scraped/dataset events wait for review; content_items dedupe
                // prevents re-creating the same pending event on later runs.
                Event::create([
                    'title' => str($item->title)->limit(250)->toString(),
                    'description' => $item->summary,
                    'starts_at' => $item->startsAt,
                    'ends_at' => $item->endsAt,
                    'location' => $item->location,
                    'url' => $item->url,
                    'organization_id' => $source->organization_id,
                    'source_id' => $source->id,
                    'status' => 'pending',
                ]);
            }
        }
    }
}
```

`routes/console.php` — add:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:fetch-sources')->fridays()->at('22:00');
```

- [ ] **Step 4: Run tests** — filter PASS, full suite green.
- [ ] **Step 5: Commit** — `feat: fetch-sources command with dedupe, event sync, and health counters`

---

### Task 7: Source-health notifications — admin banner + one email per streak

**Files:**
- Create: `app/Mail/SourceFailingMail.php`, `resources/views/mail/source-failing.blade.php`
- Modify: `app/Console/Commands/FetchSources.php` (notify hook at end of run)
- Modify: `resources/views/admin/review/index.blade.php` (failing-sources banner at top)
- Modify: `app/Http/Controllers/Admin/ReviewController.php` (pass failing sources)
- Test: `tests/Feature/SourceHealthTest.php`

**Interfaces:**
- Consumes: `Source` counters (Task 6).
- Produces: after each fetch run, every source with `consecutive_failures >= 2` and `failure_notified_at === null` triggers one `SourceFailingMail` to all admin users and gets `failure_notified_at` stamped (no weekly spam; reset on success already handled). Review-queue page shows a warning banner listing failing sources with links to their edit pages.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Mail\SourceFailingMail;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SourceHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_consecutive_failure_emails_admins_once(): void
    {
        Mail::fake();
        Http::fake(['down.example/*' => Http::response('', 500)]);
        User::factory()->create(['status' => 'active', 'role' => 'admin', 'email' => 'admin@example.org']);
        $source = Source::factory()->create([
            'type' => 'rss', 'url' => 'https://down.example/feed.xml', 'consecutive_failures' => 1,
        ]);

        $this->artisan('app:fetch-sources'); // failure #2 -> notify
        Mail::assertSent(SourceFailingMail::class, 1);
        $this->assertNotNull($source->fresh()->failure_notified_at);

        $this->artisan('app:fetch-sources'); // failure #3 -> already notified, no new mail
        Mail::assertSent(SourceFailingMail::class, 1);
    }

    public function test_first_failure_does_not_email(): void
    {
        Mail::fake();
        Http::fake(['down.example/*' => Http::response('', 500)]);
        User::factory()->create(['status' => 'active', 'role' => 'admin']);
        Source::factory()->create(['type' => 'rss', 'url' => 'https://down.example/feed.xml']);

        $this->artisan('app:fetch-sources');
        Mail::assertNothingSent();
    }

    public function test_review_queue_shows_failing_source_banner(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
        Source::factory()->create(['name' => 'Dead Feed', 'consecutive_failures' => 2]);

        $this->actingAs($admin)->get('/admin/review')
            ->assertOk()->assertSee('Dead Feed');
    }
}
```

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Implement**

`SourceFailingMail` (queueable not required; sync mail):

```php
<?php

namespace App\Mail;

use App\Models\Source;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SourceFailingMail extends Mailable
{
    public function __construct(public Source $source) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[Lake City Commons] Source failing: {$this->source->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.source-failing');
    }
}
```

`resources/views/mail/source-failing.blade.php`:

```blade
<p>The source <strong>{{ $source->name }}</strong> has failed {{ $source->consecutive_failures }} consecutive fetch runs.</p>
<p>URL: {{ $source->url }}</p>
<p>Last success: {{ $source->last_succeeded_at?->toDayDateTimeString() ?? 'never' }}</p>
<p><a href="{{ route('admin.sources.edit', $source) }}">Edit this source</a></p>
```

In `FetchSources::handle()`, after the loop:

```php
$failing = Source::active()
    ->where('consecutive_failures', '>=', 2)
    ->whereNull('failure_notified_at')
    ->get();

if ($failing->isNotEmpty()) {
    $admins = \App\Models\User::where('role', 'admin')->pluck('email');
    foreach ($failing as $source) {
        foreach ($admins as $email) {
            \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\SourceFailingMail($source));
        }
        $source->forceFill(['failure_notified_at' => now()])->save();
    }
}
```

`ReviewController::index()` — add `'failingSources' => Source::active()->where('consecutive_failures', '>=', 2)->get()` to the view data. Banner at the top of `admin/review/index.blade.php`:

```blade
@if ($failingSources->isNotEmpty())
    <div class="rounded-lg bg-amber-50 border border-amber-300 text-amber-900 px-4 py-3 text-sm mb-6">
        <p class="font-semibold">Sources failing repeatedly:</p>
        <ul class="mt-1 list-disc list-inside">
            @foreach ($failingSources as $failing)
                <li><a class="underline" href="{{ route('admin.sources.edit', $failing) }}">{{ $failing->name }}</a> — {{ $failing->consecutive_failures }} consecutive failures</li>
            @endforeach
        </ul>
    </div>
@endif
```

- [ ] **Step 4: Run tests** — filter PASS, full suite green (ReviewQueueTest must still pass — the new view variable must be provided by the controller in all paths).
- [ ] **Step 5: Commit** — `feat: source failure notifications and review-queue banner`

---

### Task 8: Digest drafter service + DraftDigest command

**Files:**
- Create: `app/Services/Digest/DigestDrafter.php` (interface), `ClaudeDigestDrafter.php`, `RawListDrafter.php`
- Create: `app/Console/Commands/DraftDigest.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind interface), `routes/console.php` (schedule)
- Test: `tests/Feature/DraftDigestCommandTest.php`

**Interfaces:**
- Produces:

```php
interface DigestDrafter
{
    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\ContentItem> $items
     * @param \Illuminate\Support\Collection<int, \App\Models\Event> $events upcoming approved events
     * @return string markdown digest body
     */
    public function draft(\Illuminate\Support\Collection $items, \Illuminate\Support\Collection $events): string;
}
```

`php artisan app:draft-digest`: collects `ContentItem::unprocessed()` fetched in the last 8 days + approved events starting within the next 10 days; skips (with notice) when there are no new items AND no upcoming events, or when a digest post with status `draft|review` already exists from the last 6 days (idempotent); calls the bound `DigestDrafter`; on ANY drafter exception falls back to `RawListDrafter` (never fails the run); creates `Post` (author: first admin user; title `Lake City This Week — {M j, Y}`; `status='review'`); marks used items `in_digest`. Scheduled Saturdays 06:00.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Services\Digest\DigestDrafter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DraftDigestCommandTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'admin']);
    }

    public function test_creates_review_draft_and_marks_items(): void
    {
        $this->admin();
        ContentItem::factory()->count(3)->create();
        Event::factory()->create(['starts_at' => now()->addDays(3)]);

        $this->app->bind(DigestDrafter::class, fn () => new class implements DigestDrafter {
            public function draft(Collection $items, Collection $events): string
            {
                return "## News\n\nDrafted digest with {$items->count()} items.";
            }
        });

        $this->artisan('app:draft-digest')->assertSuccessful();

        $post = Post::first();
        $this->assertSame('review', $post->status);
        $this->assertStringContainsString('Drafted digest with 3 items', $post->body);
        $this->assertEquals(0, ContentItem::unprocessed()->count());
        $this->assertEquals(3, ContentItem::where('status', 'in_digest')->count());
    }

    public function test_drafter_failure_falls_back_to_raw_list(): void
    {
        $this->admin();
        ContentItem::factory()->create(['title' => 'Rescue Me Item']);

        $this->app->bind(DigestDrafter::class, fn () => new class implements DigestDrafter {
            public function draft(Collection $items, Collection $events): string
            {
                throw new \RuntimeException('API down');
            }
        });

        $this->artisan('app:draft-digest')->assertSuccessful();

        $post = Post::first();
        $this->assertNotNull($post);
        $this->assertSame('review', $post->status);
        $this->assertStringContainsString('Rescue Me Item', $post->body);
    }

    public function test_skips_when_recent_digest_draft_exists(): void
    {
        $this->admin();
        ContentItem::factory()->create();
        Post::factory()->create(['status' => 'review', 'created_at' => now()->subDay()]);

        $this->artisan('app:draft-digest')->assertSuccessful();
        $this->assertEquals(1, Post::count());
    }

    public function test_skips_when_nothing_to_report(): void
    {
        $this->admin();
        $this->artisan('app:draft-digest')->assertSuccessful();
        $this->assertEquals(0, Post::count());
    }

    public function test_raw_list_drafter_groups_by_kind(): void
    {
        $items = collect([
            ContentItem::factory()->create(['kind' => 'news', 'title' => 'A News Story']),
            ContentItem::factory()->create(['kind' => 'notice', 'title' => 'A Permit Notice']),
        ]);
        $events = collect([Event::factory()->create(['title' => 'Saturday Market', 'starts_at' => now()->addDays(2)])]);

        $body = (new \App\Services\Digest\RawListDrafter)->draft($items, $events);

        $this->assertStringContainsString('A News Story', $body);
        $this->assertStringContainsString('A Permit Notice', $body);
        $this->assertStringContainsString('Saturday Market', $body);
    }
}
```

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Implement**

`RawListDrafter` (the fallback — plain grouped markdown with links):

```php
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

        foreach ($sections as $kind, $heading) {
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
```

`ClaudeDigestDrafter` — official PHP SDK, guarded content-block read, no sampling params:

```php
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
```

Bind in `AppServiceProvider::register()`:

```php
$this->app->bind(\App\Services\Digest\DigestDrafter::class, \App\Services\Digest\ClaudeDigestDrafter::class);
```

`DraftDigest` command:

```php
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

    public function handle(DigestDrafter $drafter): int
    {
        if (Post::whereIn('status', ['draft', 'review'])->where('created_at', '>=', now()->subDays(6))->exists()) {
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
            'title' => 'Lake City This Week — ' . now()->format('M j, Y'),
            'body' => $body,
            'status' => 'review',
        ]);

        ContentItem::whereIn('id', $items->pluck('id'))->update(['status' => 'in_digest']);

        $this->info("Digest drafted from {$items->count()} items and {$events->count()} events.");
        return self::SUCCESS;
    }
}
```

`routes/console.php` — add:

```php
Schedule::command('app:draft-digest')->saturdays()->at('06:00');
```

- [ ] **Step 4: Run tests** — filter PASS, full suite green.
- [ ] **Step 5: Commit** — `feat: weekly digest drafting via Claude API with raw-list fallback`

---

### Task 9: Publish action + email-ready export + markdown rendering + review-queue digest link

**Files:**
- Modify: `app/Http/Controllers/Admin/PostController.php` (publish + emailView actions), `routes/web.php` (admin routes), `resources/views/admin/posts/index.blade.php` (Publish button + "Email version" link), `resources/views/admin/review/index.blade.php` (pending digest draft card), `resources/views/news/show.blade.php` (render body as markdown)
- Create: `resources/views/admin/posts/email.blade.php`
- Test: `tests/Feature/PublishDigestTest.php`

**Interfaces:**
- Consumes: `Post` statuses (Plan 1 Task 10). Digest bodies are markdown (Task 8).
- Produces: `POST /admin/posts/{post}/publish` (`admin.posts.publish`) — sets `status=published`, `published_at` (preserved if already set), audit-logs `post_publish`. `GET /admin/posts/{post}/email` (`admin.posts.email`) — the **email-ready view**: the digest rendered as simple, email-client-safe HTML (inline styles only, no site chrome) for select-all → copy → paste into the owner's own mail client, plus the raw markdown in a `<textarea>` for plain-text sending. Markdown rendering uses Laravel's built-in `Str::markdown()` (league/commonmark — verify it's present with `composer show league/commonmark`; it ships with laravel/framework, but if absent it is pre-approved to `composer require league/commonmark`) with `['html_input' => 'escape', 'allow_unsafe_links' => false]`; the public `news/show` page switches from `whitespace-pre-line` plain text to the same safe markdown rendering. No newsletter service, no external push — `newsletter_sent_at` stays unused.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishDigestTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'admin']);
    }

    public function test_publish_action_publishes_and_audit_logs(): void
    {
        $post = Post::factory()->create(['status' => 'review', 'title' => 'Weekly Digest 1']);

        $this->actingAs($this->admin())->post("/admin/posts/{$post->id}/publish")->assertRedirect();

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'post_publish']);
    }

    public function test_republish_preserves_original_published_at(): void
    {
        $original = now()->subDays(3)->startOfSecond();
        $post = Post::factory()->create(['status' => 'review', 'published_at' => $original]);

        $this->actingAs($this->admin())->post("/admin/posts/{$post->id}/publish");

        $this->assertTrue($post->fresh()->published_at->equalTo($original));
    }

    public function test_email_view_renders_markdown_and_raw_source(): void
    {
        $post = Post::factory()->create([
            'status' => 'review',
            'body' => "## News\n\n- [Big Story](https://example.org/story) happened.",
        ]);

        $response = $this->actingAs($this->admin())->get("/admin/posts/{$post->id}/email");

        $response->assertOk();
        $response->assertSee('<h2', false);                       // markdown rendered to HTML
        $response->assertSee('https://example.org/story');
        $response->assertSee('## News');                          // raw markdown in the textarea
    }

    public function test_email_view_escapes_raw_html_in_body(): void
    {
        $post = Post::factory()->create(['body' => 'Hello <script>alert(1)</script> world']);

        $this->actingAs($this->admin())->get("/admin/posts/{$post->id}/email")
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_public_news_page_renders_markdown(): void
    {
        $post = Post::factory()->published()->create(['body' => "## Around Town\n\nA **bold** week."]);

        $response = $this->get('/news/' . $post->slug);

        $response->assertOk();
        $response->assertSee('<h2', false);
        $response->assertSee('<strong>bold</strong>', false);
    }

    public function test_email_view_blocked_for_non_admin(): void
    {
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);
        $post = Post::factory()->create();

        $this->actingAs($member)->get("/admin/posts/{$post->id}/email")->assertForbidden();
    }

    public function test_review_queue_links_pending_digest_draft(): void
    {
        Post::factory()->create(['status' => 'review', 'title' => 'Lake City This Week — Jul 25, 2026']);

        $this->actingAs($this->admin())->get('/admin/review')
            ->assertOk()->assertSee('Lake City This Week');
    }
}
```

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Implement**

`PostController` additions (`AdminAuditLog` already imported; add `use Illuminate\Support\Str;`):

```php
public function publish(Request $request, Post $post)
{
    $post->update([
        'status' => 'published',
        'published_at' => $post->published_at ?? now(),
    ]);

    AdminAuditLog::create([
        'admin_id' => $request->user()->id,
        'action' => 'post_publish',
        'payload' => ['post_id' => $post->id, 'title' => $post->title],
        'ip_address' => $request->ip(),
    ]);

    return redirect()->route('admin.posts.index')
        ->with('success', 'Post published. Use "Email version" to copy it into your mail client.');
}

public function email(Post $post)
{
    $html = Str::markdown($post->body, [
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
    ]);

    return view('admin.posts.email', compact('post', 'html'));
}
```

Routes (inside admin group):

```php
Route::post('/posts/{post}/publish', [AdminPostController::class, 'publish'])->name('posts.publish');
Route::get('/posts/{post}/email', [AdminPostController::class, 'email'])->name('posts.email');
```

`resources/views/admin/posts/email.blade.php` — deliberately minimal, email-client-safe markup (inline styles, no Tailwind inside the copy region):

```blade
<x-app-layout>
    @section('title', 'Email version — ' . $post->title)
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="font-display text-2xl text-forest mb-2">Email version</h1>
        <p class="text-sm text-earth-muted mb-6">
            Select everything inside the box below (click, then Ctrl/Cmd-A) and paste it into a
            new email. Suggested subject: <strong>{{ $post->title }}</strong>
        </p>

        <div class="bg-white rounded-lg shadow-sm p-6 mb-8" id="email-body">
            <div style="font-family: Georgia, 'Times New Roman', serif; font-size: 16px; line-height: 1.6; color: #1f2937; max-width: 640px;">
                <h1 style="font-size: 22px; margin-bottom: 4px;">{{ $post->title }}</h1>
                <p style="font-size: 13px; color: #6b7280; margin-top: 0;">
                    From <a href="{{ route('news.show', $post) }}">lakecitycommons.com</a>
                </p>
                {!! $html !!}
                <hr style="margin-top: 24px; border: none; border-top: 1px solid #d1d5db;">
                <p style="font-size: 13px; color: #6b7280;">
                    Read online: <a href="{{ route('news.show', $post) }}">{{ route('news.show', $post) }}</a>
                </p>
            </div>
        </div>

        <h2 class="font-display text-lg text-forest mb-2">Plain-text (markdown) source</h2>
        <textarea readonly rows="14" class="w-full rounded-md border-forest-pale font-mono text-xs"
                  onclick="this.select()">{{ $post->body }}</textarea>
    </div>
</x-app-layout>
```

(Adapt the outer wrapper to whatever layout convention `admin/posts/index.blade.php` actually uses — the copy-region markup with inline styles is the part that must stay as specified.)

`admin/posts/index.blade.php` — per row: when `status !== 'published'`, a "Publish" button (form POST to `admin.posts.publish`, confirm dialog "Publish this post?"); for every post, an "Email version" link to `route('admin.posts.email', $post)`.

`resources/views/news/show.blade.php` — replace the `whitespace-pre-line` body div with safe markdown rendering:

```blade
<div class="prose prose-sm mt-6 max-w-none">
    {!! \Illuminate\Support\Str::markdown($post->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
</div>
```

`admin/review/index.blade.php` — below the failing-sources banner, when a digest draft exists show a card: pass `'digestDraft' => Post::whereIn('status', ['draft', 'review'])->latest()->first()` from `ReviewController::index()`, render title + created date + "Edit draft" link to `route('admin.posts.edit', $digestDraft)` and an "Email version" link.

- [ ] **Step 4: Run tests** — filter PASS, full suite green (Plan 1's NewsPageTest must still pass — its assertions are content-based and survive the markdown switch; update any assertion that depended on `whitespace-pre-line`).
- [ ] **Step 5: Commit** — `feat: publish action, email-ready digest export, markdown rendering`

---

### Task 10: Deployment docs + final verification

**Files:**
- Modify: `README.md` (new "Production: weekly pipeline" section)
- Test: full-suite + flag-off runs (no new tests)

- [ ] **Step 1: Write the README section** — under a `## Production: weekly pipeline` heading document: (1) the single DreamHost crontab entry:

```
* * * * * cd $HOME/lakecitycommons.com && /usr/local/php84/bin/php artisan schedule:run >> /dev/null 2>&1
```

(note: use `which php` over SSH to confirm the PHP 8.4 binary path); (2) required production env var: `ANTHROPIC_API_KEY` (console.anthropic.com), noting the pipeline degrades gracefully — fetch always runs, and if the key is missing or the API call fails, the drafter's raw-list fallback still produces a reviewable draft; (3) the weekly rhythm: Fri 22:00 fetch → Sat 06:00 draft → owner reviews at `/admin/review` → Publish → open "Email version" and paste into your mail client to send manually; (4) how to add a source (admin → Sources) with one example `selector_config` for html and dataset types.

- [ ] **Step 2: Full verification** — `php artisan test --compact` (entire suite green), `FEATURE_COMMUNITY=false php artisan test --filter=FeatureFlagTest` (still green), `php artisan schedule:list` shows both commands with correct cadence.
- [ ] **Step 3: Commit** — `docs: production pipeline setup and cron documentation`

---

## Deferred (Plan 3 / later)

Newsletter automation (listmonk self-hosted or Buttondown — revisit when the subscriber list outgrows manual sending; `posts.newsletter_sent_at` is already in the schema), a public "get the digest by email" signup capture (until then the site invites readers to email the owner), reader memberships (Stripe/Cashier), sponsor ad slots, unhiding `FEATURE_COMMUNITY`, homepage hero photo (needs user-supplied photography), RFC 5545 line folding + remaining Plan-1 review minors (tracked in PR #1 body).
