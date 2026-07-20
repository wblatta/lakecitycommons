# Lake City Commons

Public neighborhood news & community site for Lake City, Seattle. The original OlyHillsHub time-banking/item-sharing platform remains in the codebase behind `FEATURE_COMMUNITY`.

A hyper-local neighborhood community platform combining time banking with item sharing. Members exchange skills and lend or gift items using a hybrid credit system. Referral-only to preserve community trust.

## What It Does

- **Time banking** — Members offer skills and earn time credits. One hour given = one hour earned.
- **Item sharing** — Members lend tools, gear, and more. Items can be offered as a **Lend** (return expected, free or credit-based) or a **Gift** (permanent, no return).
- **Referral-only access** — New members join via an invitation link from an existing member.
- **Messaging** — Direct threads between members, attached to exchange requests.
- **Admin tools** — Member management, credit adjustments, community news posts, immutable audit log.

## Tech Stack

- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: MySQL (production), SQLite in-memory (tests)
- **Frontend**: Blade + Tailwind CSS + Alpine.js
- **Auth**: Laravel Breeze (referral-gated registration)
- **File storage**: Spatie MediaLibrary (item photos)
- **Hosting target**: DreamHost shared hosting

## Local Development

### Requirements

- PHP 8.2+
- Composer
- Node.js + npm
- SQLite (for tests) or MySQL (for local dev)

### Setup

```bash
git clone https://github.com/wblatta/vernaculareconomy.git
cd vernaculareconomy

composer install
npm install

cp .env.example .env
php artisan key:generate

# SQLite (simplest)
touch database/database.sqlite
# Update .env: DB_CONNECTION=sqlite, DB_DATABASE=/absolute/path/to/database.sqlite

php artisan migrate --seed
npm run dev
php artisan serve
```

### Dev Logins (after seeding)

| Role | Email | Password |
|---|---|---|
| Admin | `admin@olyhillshub.local` | `AdminPass1!` |
| Member | `sam@olyhillshub.local` | `MemberPass1!` |

### Running Tests

```bash
php artisan test
```

Tests use SQLite in-memory (`phpunit.xml`). 4 pre-existing Breeze scaffolding tests fail (registration/profile route mismatches with the referral system) — all other tests pass.

## Key Concepts

### Item Offer Types

Items can be offered in two ways:

| Type | Return Expected | Exchange Rate |
|---|---|---|
| **Lend** | Yes | Free, Time (1hr = 1 credit), or Custom |
| **Gift** | No | None — permanently transferred |

Gifted items are archived after the exchange completes and no longer appear in browse. Lent items are marked unavailable while out and become available again when the owner marks them returned.

### Time Bank Credits

- Credits are stored as `time_bank_balance` on each user.
- Transfers are atomic (DB transaction with row-level lock).
- All admin adjustments are logged to an immutable audit log.
- Grace threshold: balance may go to −5 before further debits are blocked.
- Gift exchanges move no credits.

### Exchange Request Lifecycle

```
pending → accepted → in_progress → completed → [returned]  ← lend items only
                  ↘ declined
         ↘ cancelled (from pending or accepted)
```

### Referral Flow

Members generate a 30-day invite link → recipient registers → email verification → account active.

## Production Deployment (DreamHost)

See the deployment checklist in `PLAN.md`. Key steps:

1. `php artisan config:check` — must exit 0 before deploying
2. Set `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` in production `.env`
3. `php artisan migrate --force`
4. `php artisan config:cache && php artisan route:cache && php artisan view:cache`

### Backups

Run `./backup.sh` to dump the database and item photos to `~/olyhillshub-backups/`. See `RESTORE.md` (local only, gitignored) for restore procedure.

## Production: weekly pipeline

Lake City Commons runs a weekly content digest pipeline on a cron schedule. No newsletter service is configured — the owner reviews and publishes drafts manually, then exports the digest to send via email.

### Cron Setup (DreamHost)

Add a single entry to your `crontab -e`:

```
* * * * * cd $HOME/lakecitycommons.com && /usr/local/php84/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Note:** Use `which php` over SSH to confirm the PHP 8.4 binary path on your host. The cron entry runs every minute; Laravel's scheduler filters it down to the correct times for each command.

### Environment Variables

The pipeline requires:

- **`ANTHROPIC_API_KEY`** (required) — Obtain from [console.anthropic.com](https://console.anthropic.com). The digest drafter uses Claude to generate human-readable summaries from raw content. **Graceful degradation:** if the key is missing or the API call fails, the pipeline falls back to a raw bulleted list, still producing a reviewable draft.
- **`ANTHROPIC_MODEL`** (optional) — Defaults to `claude-sonnet-5`. Only change if you want to use a different Claude model for drafting.

### Weekly Rhythm

| Day | Time | What Happens |
|---|---|---|
| Friday | 22:00 | `app:fetch-sources` runs — pulls all active sources (RSS, HTML, ICS, datasets) and stores new content items |
| Saturday | 06:00 | `app:draft-digest` runs — draftees Claude to summarize the week's content into a review-ready post |
| Saturday+ | Manual | Visit `/admin/review` to review the draft post. Approve and publish when ready. |
| After publish | Manual | Navigate to the post and click "Email version" to open a plain-text view. Copy and paste into your mail client to send to subscribers. |

### Adding a Source

Admin panel → **Sources** → **Create**. Fill in:

- **Name** — Display name (e.g., "Enjoy Lake City")
- **URL** — Feed or API endpoint
- **Type** — One of: `rss`, `ics`, `html`, or `dataset`
- **Organization** — Link to an organization in the directory (optional)
- **Active** — Toggle to enable/disable fetching

The `selector_config` JSON depends on the source type:

#### HTML Scraper Example

For scraping event listings from an HTML page (e.g., Lake City Business Alliance event calendar):

```json
{
  "item_selector": ".event-card",
  "title_selector": ".event-title",
  "link_selector": ".event-link@href",
  "summary_selector": ".event-description",
  "starts_at_selector": ".event-date",
  "kind": "event"
}
```

- `item_selector` (required) — CSS selector for each item container
- `title_selector` (required) — CSS selector for the title within each item
- `link_selector` (optional) — CSS selector and optional attribute (format: `selector@attr`, e.g., `a@href`). Defaults to `a@href`.
- `summary_selector` (optional) — CSS selector for description/summary text
- `starts_at_selector` (optional) — CSS selector for date/time; parsed with Carbon
- `kind` (optional) — Set to `event` if these are events; defaults to `news`

#### Dataset Example

For a JSON API returning events (e.g., municipal or nonprofit calendar endpoint):

```json
{
  "items_path": "data.events",
  "title_field": "name",
  "url_field": "website",
  "date_field": "published_date",
  "starts_at_field": "event_date",
  "summary_field": "description",
  "kind": "event"
}
```

- `items_path` (optional) — JSON path to the array of records (e.g., `data.events`). If omitted, the root response is treated as an array.
- `title_field` (required) — Field name for the item title
- `url_field` (optional) — Field name for the item URL
- `date_field` (optional) — Field name for publish date; parsed with Carbon
- `starts_at_field` (optional) — Field name for event start time; parsed with Carbon
- `summary_field` (optional) — Field name for description/summary
- `kind` (optional) — Set to `event` for event records; defaults to `notice`

## Project Structure

```
app/
  Console/Commands/ConfigCheck.php   # Production config validator
  Http/Controllers/
    Admin/                           # Member management, audit log, posts
    Auth/                            # Referral-gated registration
    ItemController.php               # Gift/lend items, toggle guard
    SkillController.php
    ExchangeRequestController.php
    MessageController.php
  Http/Middleware/
    SecurityHeaders.php              # CSP, X-Frame-Options, etc.
    EnsureReferralToken.php
    EnsureActiveUser.php
    AdminOnly.php
  Models/
    User.php, Item.php, Skill.php
    ExchangeRequest.php, Transaction.php
    Thread.php, Message.php
    AdminAuditLog.php                # Immutable admin action ledger
    ReferralToken.php, WaitlistEntry.php
  Services/
    CreditService.php                # Balance transfers (lockForUpdate)
    RequestService.php               # State machine + item side effects
    ReferralService.php
    MessageService.php
docs/
  superpowers/
    specs/                           # Feature design documents
    plans/                           # Implementation plans
```

> **Server PHP requirement:** `short_open_tag = Off` (set in `~/.php/8.4/phprc` on DreamHost). With short tags on, Blade mis-compiles any template containing a literal `<?xml` declaration (the RSS feed), producing a 500 or garbage first line. After changing phprc: `pkill -f php84` and clear compiled views.
