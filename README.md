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
