# OlyHillsHub — Hyper-Local Community Platform

## Context

Building a neighborhood community platform grounded in vernacular economy principles (Latta capstone), combining time banking (TimeBanks.org model) with item-sharing (MyTurn model). Members exchange skills and items using a hybrid credit system. The platform is referral-only to preserve community trust. Hosted on DreamHost shared hosting; must remain simple to maintain for a solo developer.

---

## Stack

- **Framework**: Laravel 12.x (PHP 8.2+)
- **Database**: MySQL (DreamHost managed)
- **Frontend**: Blade templates + Tailwind CSS + Alpine.js
- **Auth scaffolding**: Laravel Breeze
- **Messaging**: AJAX polling (no WebSockets on shared hosting)
- **Queue**: `database` driver, cron-based `queue:work --stop-when-empty`
- **Cache/Session**: `file` driver

---

## Database Schema

### `users`
```
id, name, email, password, email_verified_at, avatar, bio,
neighborhood_area, time_bank_balance (decimal 10,2 default 0),
status (enum: active|suspended|pending), role (enum: member|admin),
referred_by (FK → users.id nullable), remember_token, timestamps
```

### `referral_tokens`
```
id, token (varchar 64 unique), inviter_id (FK → users.id),
invitee_email (nullable), used_at (nullable),
used_by_user_id (FK → users.id nullable),
expires_at, timestamps
```

### `categories`
```
id, name, type (enum: skill|item|both), slug (unique), timestamps
```

### `skills`
```
id, user_id (FK), title, description, category_id (FK),
credit_type (enum: gift|time_equal|custom),
custom_credit_value (decimal nullable), is_available (bool), timestamps
```

### `items`
```
id, user_id (FK), title, description, category_id (FK),
condition (enum: excellent|good|fair|poor),
credit_type (enum: gift|time_equal|custom),
custom_credit_value (decimal nullable), is_available (bool), timestamps
```

### `item_photos`
```
id, item_id (FK), path, sort_order, timestamps
```

### `availability_schedules`
```
id, resource_type (enum: skill|item), resource_id,
recurrence (enum: weekly|specific),
day_of_week (tinyint nullable), specific_date (date nullable),
start_time, end_time, is_blocked (bool), timestamps
```

### `requests` (model class: `ExchangeRequest`)
```
id, requester_id (FK), owner_id (FK),
resource_type (enum: skill|item), resource_id,
proposed_datetime, duration_hours (decimal nullable),
message (text nullable),
status (enum: pending|accepted|in_progress|completed|declined|cancelled),
credit_type (snapshot), credit_value (snapshot, decimal),
requester_confirmed_at, owner_confirmed_at, completed_at, timestamps
```

### `transactions`
```
id, request_id (FK nullable), from_user_id (FK nullable),
to_user_id (FK), amount (decimal), type (enum: credit|debit|adjustment),
note (varchar nullable), timestamps
```
> Append-only. Never update rows. Balance = sum of debits/credits.

### `threads`
```
id, request_id (FK nullable), subject (nullable), timestamps
```

### `thread_participants`
```
id, thread_id (FK), user_id (FK), last_read_at (nullable), timestamps
UNIQUE(thread_id, user_id)
```

### `messages`
```
id, thread_id (FK), sender_id (FK), body (text), timestamps
```

---

## Key Service Classes

| File | Responsibility |
|---|---|
| `app/Services/ReferralService.php` | Generate/validate tokens |
| `app/Services/CreditService.php` | Debit/credit logic, balance update (DB transaction) |
| `app/Services/RequestService.php` | State machine transitions |
| `app/Services/AvailabilityService.php` | Conflict detection, calendar grid |
| `app/Services/MessageService.php` | Thread creation, polling query |

---

## Key Middleware

| File | Purpose |
|---|---|
| `app/Http/Middleware/EnsureReferralToken.php` | Block `/register/{token}` if token invalid/expired |
| `app/Http/Middleware/AdminOnly.php` | Restrict admin routes |

---

## Referral Flow

1. Member visits "Invite" page → `ReferralService::generate()` creates a token row (`expires_at = now() + 30 days`)
2. Shareable URL: `https://site.com/register/{token}`
3. `EnsureReferralToken` middleware validates before form renders
4. On `POST /register/{token}`: validate fields → create `User` (status=pending) + mark token used atomically in DB transaction
5. Fire `Registered` event → verification email sent
6. On email verification: listener on `Verified` event sets `status = active`

---

## Request / Exchange / Credit Flow

1. **Create**: Requester submits form → `CreditService::calculateCreditValue()` snapshots `credit_type` + `credit_value` onto request row → thread + first message created
2. **Accept/Decline**: Owner responds → `RequestService::transition()` enforces allowed state changes
3. **Dual Confirmation**: Both parties click "Confirm Completion" independently
4. **Credit Transfer** (inside DB transaction):
   - `gift`: no ledger entries, status → completed
   - `time_equal` / `custom`: check requester balance ≥ credit_value → insert two `transactions` rows → `decrement`/`increment` `time_bank_balance` → status → completed
5. **Cancellation**: Either party cancels `pending`/`accepted` → no credits move
6. **Admin adjustments**: Manual `transactions` rows with `type = adjustment`

---

## Availability System

- Weekly recurring: `recurrence=weekly`, `day_of_week`, time window, `is_blocked=0`
- Date block: `recurrence=specific`, `specific_date`, `is_blocked=1` (explicitly unavailable)
- `AvailabilityService::getAvailabilityForResource()`: builds 4-week grid, specific blocks override weekly schedule
- `AvailabilityService::isAvailable()`: checks window + no conflicting accepted/in_progress request
- Frontend: Blade component passes JSON array to Alpine.js for calendar grid (no JS library needed)

---

## Messaging (AJAX Polling)

- 5-second poll on `/messages/{thread}/poll?after={lastId}` — returns only new messages
- 15-second poll on `/messages/unread-count` from global layout Alpine component
- No WebSockets; acceptable latency for neighborhood-scale use

---

## Recommended Packages

```
laravel/breeze          # Auth scaffolding
spatie/laravel-permission  # Admin roles
spatie/laravel-medialibrary  # Item photo uploads + resizing
spatie/laravel-sluggable     # Clean URLs
barryvdh/laravel-debugbar    # Dev only
```

---

## Testing Strategy

### Approach: Feature-first, database-backed tests (no mocks for DB)

Use Laravel's built-in `RefreshDatabase` trait. Tests hit a real SQLite (local) or MySQL test database — never mock the DB layer, as mock/prod divergence masks migration issues.

### Test Database
- Local dev: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in `phpunit.xml` for fast in-memory tests
- CI (if added later): dedicated MySQL test database

### Test Layers

**Feature Tests** (primary layer — covers full HTTP request → response cycle):
```
tests/Feature/
  Auth/
    ReferralRegistrationTest.php   # valid token, expired token, used token, no token
    EmailVerificationTest.php
  Skills/
    SkillCrudTest.php              # create, update, delete, authorization
  Items/
    ItemCrudTest.php
    ItemPhotoUploadTest.php
  Requests/
    ExchangeRequestFlowTest.php    # full lifecycle: create → accept → confirm → complete
    CreditTransferTest.php         # gift (no debit), time_equal, custom, insufficient balance
    RequestStateTransitionTest.php # invalid transitions rejected
  Availability/
    AvailabilityConflictTest.php   # conflict detection, weekly/specific override logic
  Messaging/
    ThreadCreationTest.php
    MessagePollTest.php
  Admin/
    MemberManagementTest.php       # suspend, activate, referral chain view
```

**Unit Tests** (pure logic, no DB):
```
tests/Unit/
  CreditServiceTest.php            # calculateCreditValue for all 3 credit types
  AvailabilityServiceTest.php      # calendar grid logic, edge cases (DST, no schedule)
  RequestServiceTest.php           # state machine: all valid/invalid transitions
  ReferralServiceTest.php          # token generation format, expiry calculation
```

**Key Test Cases to Cover**:
- Cannot register without valid referral token (403)
- Expired token rejected; used token rejected
- Credit transfer is atomic — partial failure leaves balances unchanged
- Balance cannot go below -5 (grace threshold)
- Duplicate request for same resource/time window rejected
- Non-owner cannot accept/decline another member's request
- Admin can access member management; non-admin cannot

### Running Tests
```bash
php artisan test                          # all tests
php artisan test --filter CreditTransfer  # targeted
php artisan test --parallel               # faster on local (needs ext-pcntl)
```

### CI (optional but recommended)
Add a GitHub Actions workflow (`.github/workflows/test.yml`) that runs `php artisan test` on every push. DreamHost does not run CI; this runs on GitHub's free tier.

---

## Backup Strategy

### What Needs Backing Up
1. **MySQL database** — all application data (members, transactions, requests, messages)
2. **Uploaded files** — item photos in `storage/app/public`
3. **`.env` file** — production config (store separately, never in git)

### Database Backups

**DreamHost built-in**: Enable "Automatic MySQL Backups" in the DreamHost panel (Panel → MySQL Databases). DreamHost keeps 1 daily snapshot; this is a baseline safety net only.

**Custom daily backup via cron** (more reliable, off-server copy):
```bash
# DreamHost cron — runs daily at 2am
mysqldump -h mysql.yourdomain.com -u dbuser -p'password' dbname \
  | gzip > /home/username/backups/db_$(date +%Y%m%d).sql.gz
```
Retain 14 daily snapshots; delete older:
```bash
find /home/username/backups/ -name "db_*.sql.gz" -mtime +14 -delete
```

**Off-server copy**: Sync backups to an off-server destination nightly. Options:
- `rclone` to a free-tier **Backblaze B2** bucket (2GB free, cheap after)
- `rclone` to **Google Drive** (15GB free)
- `rsync` to a second server or local NAS

Example rclone cron:
```bash
rclone sync /home/username/backups/ b2:your-bucket-name/db-backups/
```

### File Backups

Item photos are stored in `storage/app/public`. Include in nightly rclone sync:
```bash
rclone sync /home/username/your-project/storage/app/public/ b2:your-bucket-name/uploads/
```

### Code / Config Backups

- **Code**: Git is the backup. Keep the repo on GitHub (or GitLab). Every deploy is a tagged commit.
- **`.env`**: Store a copy in a **password manager** (Bitwarden, 1Password) — never in git. Update it there whenever you rotate credentials.

### Backup Verification

Run a restore test quarterly:
```bash
# Restore to a local dev DB to verify integrity
gunzip < db_20260101.sql.gz | mysql -u root localtest_db
php artisan migrate:status   # confirm schema matches
```

### Recovery Plan

| Scenario | Recovery |
|---|---|
| Accidental data deletion | Restore yesterday's `mysqldump` to prod DB |
| Corrupted uploads | Pull from rclone/B2 backup |
| Bad deploy breaks site | `git revert` + redeploy; or `git checkout` last good tag |
| Full server loss | Provision new DreamHost domain, restore DB + uploads from B2, redeploy from git |

### Uptime / Stability

DreamHost shared hosting provides no SLA. For a neighborhood platform this is acceptable. Mitigations:
- Enable DreamHost's free **SSL (Let's Encrypt)** — auto-renews; configure in panel
- Set up **UptimeRobot** (free tier) to ping the site every 5 minutes and email/SMS on downtime
- Use `php artisan config:cache && php artisan route:cache` after every deploy to reduce per-request overhead on shared hardware

---

## DreamHost Deployment Checklist

1. Set PHP version to 8.2+ in panel
2. Set domain web root to `your-project/public`
3. Set `DB_HOST=mysql.yourdomain.com` in `.env`
4. `composer install --no-dev --optimize-autoloader`
5. `php artisan migrate --force`
6. `php artisan storage:link`
7. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
8. `chmod -R 755 storage bootstrap/cache`
9. Add cron jobs: `schedule:run` and `queue:work --stop-when-empty` (both every minute)
10. Add daily backup cron + rclone sync

---

## Implementation Sequence (Solo Dev)

| Week | Focus |
|---|---|
| 1–2 | Scaffolding + Auth + Referral system |
| 3 | Profiles + Skills + Items CRUD |
| 4 | Availability system + calendar widget |
| 5 | Request system + credit transfer |
| 6 | Messaging (threads + AJAX polling) |
| 7 | Admin dashboard |
| 8 | Testing suite + DreamHost deploy + backup setup |

---

## Verification

End-to-end test checklist before launch:
- [ ] Register via referral link → verify email → land on dashboard
- [ ] Create a skill (gift, time_equal, custom) and item with photo
- [ ] Set weekly availability; verify calendar renders correctly
- [ ] Make a request → accept → dual confirm → verify credit balances updated
- [ ] Make a gift request → confirm → verify no credits moved
- [ ] Attempt request outside availability window → rejected
- [ ] Send direct message; verify polling delivers it within 5 seconds
- [ ] Suspend a member as admin → verify they cannot log in
- [ ] Run `php artisan test` — all green
- [ ] Run manual mysqldump → restore to local DB → verify row counts match
