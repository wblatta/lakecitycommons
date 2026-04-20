# Security & Reliability Hardening — OlyHillsHub

**Date:** 2026-04-20
**Scope:** Account security (A), internal abuse prevention (B), external attack hardening (C), and manual backup tooling for pre-DreamHost-cron phase.
**Out of scope:** 2FA, anomaly detection, WebSocket security, CI/CD pipeline.

---

## 1. Production Config & Session Hardening

### Problem
`.env.example` ships with `APP_DEBUG=true` and no session security flags set. These must be correct in production — a misconfigured deploy leaks stack traces and allows session hijacking over HTTP.

### Solution
Document required production `.env` values and add an Artisan command to verify them at deploy time.

**Required production `.env` values:**
```
APP_DEBUG=false
APP_ENV=production
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
SESSION_ENCRYPT=true
LOG_CHANNEL=daily
```

**New Artisan command:** `php artisan config:check`
- Reads current resolved config values
- Warns (with colored output) if `APP_DEBUG=true`, `APP_ENV != production`, `SESSION_SECURE_COOKIE != true`, or `SESSION_SAME_SITE != strict`
- Exits with code 1 if any critical flag is wrong (allows use in deploy scripts)
- Safe to run in dev (warns but does not abort)

Add `php artisan config:check` to the DreamHost Deployment Checklist in `PLAN.md`.

`LOG_CHANNEL=daily` switches Laravel from a single unbounded log file to daily-rotated files — important on shared hosting where disk is limited.

---

## 2. Security Headers Middleware

### Problem
No HTTP security headers are set. Planned in `PLAN.md` but not implemented.

### Solution
New middleware: `app/Http/Middleware/SecurityHeaders.php`, appended to the `web` group in `bootstrap/app.php`.

**Headers set:**
```
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:
```

**CSP rationale:** `unsafe-inline` is required because Alpine.js uses inline event handlers and Blade templates use inline `<style>` blocks. `unsafe-eval` is required because Alpine.js v3 dynamically evaluates expressions at runtime — without it, all Alpine reactivity silently breaks. The policy still blocks external script injection (the primary XSS escalation path). No CDN sources are needed — fonts and icons are self-hosted via npm/Vite.

No configuration needed — headers are hardcoded in the middleware. If CSP needs loosening for a specific feature, it's a one-line edit.

---

## 3. Credit Transfer Race Condition Fix

### Problem
`CreditService::canAfford()` reads `time_bank_balance` without a row lock. Two simultaneous requests for the same user can both pass the balance check inside separate transactions, each completing a debit. Net result: balance drops below the grace threshold (`-5.0`) without either transaction detecting it.

### Solution
Add `lockForUpdate()` to the balance read **inside** the existing `DB::transaction()` in `CreditService::transfer()`. The lock is released when the transaction commits.

```php
// CreditService::transfer() — inside DB::transaction()
$balance = DB::table('users')
    ->where('id', $request->requester_id)
    ->lockForUpdate()
    ->value('time_bank_balance');

if (($balance - $amount) < self::GRACE_THRESHOLD) {
    throw new \RuntimeException('Insufficient balance for credit transfer.');
}
```

Remove the standalone `canAfford()` call from `transfer()` — it is replaced by this locked read. The public `canAfford()` method is retained for pre-flight UI checks (e.g., showing a warning before submit) but is not used as the authoritative check.

**MySQL note:** `SELECT ... FOR UPDATE` requires InnoDB, which is the default on DreamHost MySQL. SQLite (used in tests) does not support `FOR UPDATE` — use `DB::connection()->getDriverName()` check in tests, or run credit transfer tests against MySQL.

---

## 4. Rate Limiting

### Problem
Only the login endpoint has rate limiting. Registration, password reset, and the message polling endpoint are unprotected.

### Solution
Add route-level throttle middleware. No new classes needed.

| Endpoint | Limit | Key |
|---|---|---|
| `POST /register/{token}` | 5/min per IP | IP |
| `POST /forgot-password` | 3/min per IP | IP |
| `GET /messages/{thread}/poll` | 30/min per user | user ID |

Applied in `routes/web.php` and `routes/auth.php`.

Registration and password reset use `throttle:n,m` (IP-keyed, Laravel built-in). The poll endpoint requires a named rate limiter (defined in `AppServiceProvider::boot()`) keyed on authenticated user ID, since `throttle:n,m` defaults to IP — which would wrongly block all members on a shared household connection.

---

## 5. Admin Audit Log

### Problem
Admin credit adjustments and member status changes leave no trail. In a trust-based community, disputes over balance changes cannot be investigated without a record.

### Solution

**New migration:** `admin_audit_logs` table
```
id                  bigint unsigned PK
admin_id            FK → users.id
target_user_id      FK → users.id (nullable — for non-member-specific actions)
action              varchar(64): status_change | credit_adjustment | post_create | post_delete
payload             json (before/after values)
ip_address          varchar(45)
created_at          timestamp (no updated_at — append-only)
```

No `updated_at`. No soft deletes. No admin UI to edit or delete rows. This is an immutable ledger.

**New model:** `AdminAuditLog` — `$guarded = []`, no mass-assignment protection needed since it's only written by internal service code.

**Write points:**
- `MemberController::updateStatus()` — logs `status_change` with `{before: old_status, after: new_status}`
- `MemberController::adjustCredits()` — logs `credit_adjustment` with `{amount, note, balance_before, balance_after}` after calling `CreditService::adjust()`. Logging happens in the controller (not the service) because only the controller has the authenticated admin context.
- `AdminPostController::store()` / `destroy()` — logs `post_create` / `post_delete`

Written inside existing DB transactions where applicable so log entries roll back if the action fails.

**New route:** `GET /admin/audit-log` — paginated table (25/page), filterable by admin and action type. Visible to admins only.

**Credit adjustment guard:** `MemberController::adjustCredits()` adds validation `amount` must be between -100 and +100 per single adjustment. Prevents runaway manipulation. Larger adjustments require multiple intentional entries.

---

## 6. Public Profile Privacy

### Problem
`GET /users/{user}` is unauthenticated. It exposes `neighborhood_area` and `cross_streets` to anyone — including people who were never invited to the community.

### Solution
- Move `Route::get('/users/{user}', ...)` inside the `['auth', 'verified']` middleware group.
- In `UserProfileController::show()`, hide `cross_streets` from all viewers except the profile owner and admins. `neighborhood_area` (general area, not a precise location) remains visible to authenticated members.

Unauthenticated visitors are redirected to `/login` via Laravel's standard auth redirect.

---

## 7. Backup Script

### Problem
No backup tooling exists. Manual backups before deploying to DreamHost require knowing the right commands under pressure.

### Solution
A `backup.sh` script at the project root, committed to git. Credentials are never hardcoded — they are read from `.env` at runtime.

**What it backs up:**
1. MySQL database via `mysqldump` → gzipped to `~/olyhillshub-backups/db_YYYYMMDD_HHMMSS.sql.gz`
2. Item photos: `storage/app/public/` → copied to `~/olyhillshub-backups/uploads/YYYYMMDD_HHMMSS/`

**Script behavior:**
- Reads `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from `.env` using `grep`/`sed` (no extra dependencies)
- Creates `~/olyhillshub-backups/` if it doesn't exist
- Prints file path and size on completion
- Exits non-zero on failure (safe for future cron use)

**Retention:** The script does not auto-delete old backups. Manual cleanup for now; when moved to DreamHost cron, add `find ~/olyhillshub-backups -name "db_*.gz" -mtime +14 -delete`.

**Restore procedure** is documented in `RESTORE.md` at the project root (gitignored — contains production DB credentials in examples; keep locally). Covers:
- Restore DB from dump: `gunzip < file.sql.gz | mysql -h host -u user -p dbname`
- Restore uploads: `rsync` or `scp` back to `storage/app/public/`
- Post-restore: `php artisan config:cache && php artisan route:cache`

**DreamHost cron upgrade path:** When ready, add one cron entry:
```
0 2 * * * /path/to/project/backup.sh >> /home/username/backup.log 2>&1
```
No script changes needed.

---

## Implementation Order

1. Production config + `config:check` command (no risk, pure addition)
2. Security headers middleware (no risk, pure addition)
3. Rate limiting (no risk, pure addition)
4. Public profile auth gate (low risk — only affects unauthenticated access)
5. Credit transfer race condition fix (moderate — test carefully in staging)
6. Admin audit log (migration + model + controller changes)
7. Backup script

---

## Files Changed / Created

| File | Change |
|---|---|
| `app/Console/Commands/ConfigCheck.php` | New |
| `app/Http/Middleware/SecurityHeaders.php` | New |
| `app/Services/CreditService.php` | Add `lockForUpdate()` to transfer |
| `app/Models/AdminAuditLog.php` | New |
| `app/Http/Controllers/Admin/MemberController.php` | Add audit logging, credit amount guard |
| `app/Http/Controllers/Admin/PostController.php` | Add audit logging |
| `app/Http/Controllers/Admin/AuditLogController.php` | New |
| `app/Providers/AppServiceProvider.php` | Register poll rate limiter keyed on user ID |
| `app/Http/Controllers/UserProfileController.php` | Hide cross_streets from non-owners |
| `bootstrap/app.php` | Register SecurityHeaders middleware |
| `routes/web.php` | Move profile route behind auth, add throttle |
| `routes/auth.php` | Add throttle to registration + password reset |
| `database/migrations/..._create_admin_audit_logs_table.php` | New |
| `resources/views/admin/audit-log/index.blade.php` | New |
| `backup.sh` | New |
| `RESTORE.md` | New (gitignored) |
| `.gitignore` | Add `RESTORE.md` |
| `PLAN.md` | Add `config:check` to deploy checklist |
