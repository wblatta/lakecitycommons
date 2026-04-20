# Security & Reliability Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harden OlyHillsHub against account takeover, internal abuse, and external attacks, and add a manual backup script for pre-production use.

**Architecture:** Seven independent hardening areas applied in ascending risk order — pure additions first (commands, middleware, rate limits), then data-touching changes (race condition fix, audit log migration), then backup tooling. Each task is self-contained and commits cleanly.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL (InnoDB), Blade + Alpine.js, Tailwind CSS. Tests use PHPUnit with `RefreshDatabase` (SQLite in-memory for most tests; the race condition task notes a MySQL-only caveat).

**Spec:** `docs/superpowers/specs/2026-04-20-security-reliability-design.md`

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `app/Console/Commands/ConfigCheck.php` | Create | Artisan command that validates prod config values |
| `app/Http/Middleware/SecurityHeaders.php` | Create | Sets X-Frame-Options, CSP, etc. on every response |
| `app/Providers/AppServiceProvider.php` | Modify | Register named rate limiter for message poll |
| `routes/auth.php` | Modify | Add throttle to forgot-password POST |
| `routes/web.php` | Modify | Move profile route behind auth; add poll throttle; add register throttle |
| `bootstrap/app.php` | Modify | Register SecurityHeaders in web middleware group |
| `app/Http/Controllers/UserProfileController.php` | Modify | Hide cross_streets from non-owners/non-admins |
| `app/Services/CreditService.php` | Modify | Add `lockForUpdate()` inside transfer transaction |
| `database/migrations/xxxx_create_admin_audit_logs_table.php` | Create | Append-only audit log table |
| `app/Models/AdminAuditLog.php` | Create | Eloquent model for audit log (no timestamps auto-managed) |
| `app/Http/Controllers/Admin/MemberController.php` | Modify | Write audit log entries; guard credit amount -100..100 |
| `app/Http/Controllers/Admin/PostController.php` | Modify | Write audit log entries for create/delete |
| `app/Http/Controllers/Admin/AuditLogController.php` | Create | Paginated, filterable audit log view |
| `resources/views/admin/audit-log/index.blade.php` | Create | Audit log table UI |
| `tests/Feature/ConfigCheckTest.php` | Create | Command output assertions |
| `tests/Feature/SecurityHeadersTest.php` | Create | Assert headers present on web responses |
| `tests/Feature/RateLimitingTest.php` | Create | Assert 429 after threshold on each endpoint |
| `tests/Feature/ProfilePrivacyTest.php` | Create | Assert cross_streets hidden; unauthed redirect |
| `tests/Feature/Admin/AuditLogTest.php` | Create | Assert log entries written and view renders |
| `backup.sh` | Create | Shell script: mysqldump + uploads copy |
| `.gitignore` | Modify | Add `RESTORE.md` |
| `PLAN.md` | Modify | Add `php artisan config:check` to deploy checklist |

---

## Task 1: `config:check` Artisan Command

**Files:**
- Create: `app/Console/Commands/ConfigCheck.php`
- Create: `tests/Feature/ConfigCheckTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ConfigCheckTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfigCheckTest extends TestCase
{
    public function test_passes_when_production_config_is_correct(): void
    {
        config(['app.debug' => false, 'app.env' => 'production',
                'session.secure' => true, 'session.same_site' => 'strict']);

        $this->artisan('config:check')->assertExitCode(0);
    }

    public function test_fails_when_debug_is_true(): void
    {
        config(['app.debug' => true, 'app.env' => 'production',
                'session.secure' => true, 'session.same_site' => 'strict']);

        $this->artisan('config:check')->assertExitCode(1);
    }

    public function test_exits_zero_in_dev_even_with_warnings(): void
    {
        config(['app.debug' => true, 'app.env' => 'local',
                'session.secure' => false, 'session.same_site' => 'lax']);

        // warnings, but non-production env — exits 0 when not in production
        $this->artisan('config:check')->assertExitCode(0);
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

```bash
php artisan test --filter ConfigCheckTest
```
Expected: FAIL — `config:check` command not found.

- [ ] **Step 3: Create the command**

```php
// app/Console/Commands/ConfigCheck.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConfigCheck extends Command
{
    protected $signature = 'config:check';
    protected $description = 'Verify production configuration values are set correctly';

    public function handle(): int
    {
        $isProduction = config('app.env') === 'production';
        $issues = [];

        if (config('app.debug')) {
            $issues[] = ['APP_DEBUG', 'true', 'false', 'critical'];
        }
        if (!config('session.secure')) {
            $issues[] = ['SESSION_SECURE_COOKIE', 'false', 'true', 'critical'];
        }
        if (config('session.same_site') !== 'strict') {
            $issues[] = ['SESSION_SAME_SITE', config('session.same_site'), 'strict', 'warning'];
        }
        if (!$isProduction) {
            $this->warn('APP_ENV is not "production" — skipping critical exit.');
        }

        if (empty($issues)) {
            $this->info('All production config checks passed.');
            return self::SUCCESS;
        }

        $hasCritical = false;
        foreach ($issues as [$key, $current, $expected, $severity]) {
            if ($severity === 'critical') {
                $this->error("CRITICAL: {$key} is '{$current}', expected '{$expected}'");
                $hasCritical = true;
            } else {
                $this->warn("WARNING: {$key} is '{$current}', expected '{$expected}'");
            }
        }

        return ($hasCritical && $isProduction) ? self::FAILURE : self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter ConfigCheckTest
```
Expected: 3 tests pass.

- [ ] **Step 5: Add `config:check` to the deploy checklist in PLAN.md**

Find the `## DreamHost Deployment Checklist` section and add as item 1 (before all others):

```markdown
0. Run `php artisan config:check` — must exit 0 before proceeding
```

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ConfigCheck.php tests/Feature/ConfigCheckTest.php PLAN.md
git commit -m "feat: add config:check artisan command for production safety"
```

---

## Task 2: Security Headers Middleware

**Files:**
- Create: `app/Http/Middleware/SecurityHeaders.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Feature/SecurityHeadersTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/SecurityHeadersTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_present_on_web_response(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_csp_blocks_external_default_src(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

```bash
php artisan test --filter SecurityHeadersTest
```
Expected: FAIL — headers not present.

- [ ] **Step 3: Create the middleware**

```php
// app/Http/Middleware/SecurityHeaders.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:"
        );

        return $response;
    }
}
```

- [ ] **Step 4: Register in bootstrap/app.php**

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware)`, add after the existing `$middleware->alias([...])` block:

```php
$middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
```

The full `withMiddleware` closure should look like:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'referral' => EnsureReferralToken::class,
        'admin'    => AdminOnly::class,
        'active'   => EnsureActiveUser::class,
    ]);

    $middleware->appendToGroup('web', EnsureActiveUser::class);
    $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
})
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter SecurityHeadersTest
```
Expected: 2 tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/SecurityHeaders.php bootstrap/app.php tests/Feature/SecurityHeadersTest.php
git commit -m "feat: add security headers middleware (CSP, X-Frame-Options, etc.)"
```

---

## Task 3: Rate Limiting

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/auth.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/RateLimitingTest.php`

- [ ] **Step 1: Write the failing tests**

```php
// tests/Feature/RateLimitingTest.php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_rate_limited_after_3_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => 'test@example.com']);
        }

        $response = $this->post('/forgot-password', ['email' => 'test@example.com']);
        $response->assertStatus(429);
    }

    public function test_message_poll_rate_limited_after_30_attempts(): void
    {
        $user = User::factory()->create(['status' => 'active', 'role' => 'member']);
        $this->actingAs($user);

        // Create a thread the user participates in (no Thread factory exists — create directly)
        $thread = \App\Models\Thread::create(['request_id' => null, 'subject' => 'Test']);
        $thread->participants()->create(['user_id' => $user->id]);

        for ($i = 0; $i < 30; $i++) {
            $this->get("/messages/{$thread->id}/poll?after=0");
        }

        $response = $this->get("/messages/{$thread->id}/poll?after=0");
        $response->assertStatus(429);
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

```bash
php artisan test --filter RateLimitingTest
```
Expected: FAIL — no rate limiting in place.

- [ ] **Step 3: Register the named poll rate limiter in AppServiceProvider**

```php
// app/Providers/AppServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(Verified::class, function (Verified $event) {
            if ($event->user->status === 'pending') {
                $event->user->update(['status' => 'active']);
            }
        });

        RateLimiter::for('message-poll', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
```

- [ ] **Step 4: Add throttle to forgot-password in routes/auth.php**

Change:

```php
Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
    ->name('password.email');
```

To:

```php
Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('throttle:3,1')
    ->name('password.email');
```

- [ ] **Step 5: Add throttle to registration and poll in routes/web.php**

Change the referral registration block from:

```php
Route::middleware('referral')->group(function () {
    Route::get('/register/{token}', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register/{token}', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'store']);
});
```

To:

```php
Route::middleware('referral')->group(function () {
    Route::get('/register/{token}', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register/{token}', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1');
});
```

Change the poll route from:

```php
Route::get('/messages/{thread}/poll', [MessageController::class, 'poll'])->name('messages.poll');
```

To:

```php
Route::get('/messages/{thread}/poll', [MessageController::class, 'poll'])
    ->middleware('throttle:message-poll')
    ->name('messages.poll');
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --filter RateLimitingTest
```
Expected: 2 tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Providers/AppServiceProvider.php routes/auth.php routes/web.php tests/Feature/RateLimitingTest.php
git commit -m "feat: add rate limiting to registration, password reset, and message poll"
```

---

## Task 4: Public Profile Privacy

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/UserProfileController.php`
- Create: `tests/Feature/ProfilePrivacyTest.php`

- [ ] **Step 1: Write the failing tests**

```php
// tests/Feature/ProfilePrivacyTest.php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $member = User::factory()->create(['status' => 'active', 'cross_streets' => 'Oak & Maple']);

        $this->get("/users/{$member->id}")->assertRedirect('/login');
    }

    public function test_authenticated_member_cannot_see_cross_streets_of_another_member(): void
    {
        $viewer = User::factory()->create(['status' => 'active']);
        $member = User::factory()->create(['status' => 'active', 'cross_streets' => 'Oak & Maple']);

        $response = $this->actingAs($viewer)->get("/users/{$member->id}");

        $response->assertOk();
        $response->assertDontSee('Oak & Maple');
    }

    public function test_owner_can_see_their_own_cross_streets(): void
    {
        $member = User::factory()->create(['status' => 'active', 'cross_streets' => 'Oak & Maple']);

        $response = $this->actingAs($member)->get("/users/{$member->id}");

        $response->assertOk();
        $response->assertSee('Oak & Maple');
    }

    public function test_admin_can_see_any_cross_streets(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
        $member = User::factory()->create(['status' => 'active', 'cross_streets' => 'Oak & Maple']);

        $response = $this->actingAs($admin)->get("/users/{$member->id}");

        $response->assertOk();
        $response->assertSee('Oak & Maple');
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

```bash
php artisan test --filter ProfilePrivacyTest
```
Expected: FAIL — unauthenticated access currently allowed.

- [ ] **Step 3: Move the profile route inside the auth middleware group in routes/web.php**

Remove this line from the top-level (unauthenticated) area:

```php
// Public user profiles (no auth required to view)
Route::get('/users/{user}', [UserProfileController::class, 'show'])->name('users.show');
```

Add it inside the `Route::middleware(['auth', 'verified'])->group(function () {` block, at the top of that group:

```php
// Member profiles (auth required)
Route::get('/users/{user}', [UserProfileController::class, 'show'])->name('users.show');
```

- [ ] **Step 4: Update UserProfileController to hide cross_streets**

```php
// app/Http/Controllers/UserProfileController.php
<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserProfileController extends Controller
{
    public function show(User $user)
    {
        $user->load([
            'skills' => fn($q) => $q->with('category')->where('is_available', true),
            'items'  => fn($q) => $q->with('category')->where('is_available', true),
        ]);

        $viewer = auth()->user();
        $canSeeCrossStreets = $viewer->id === $user->id || $viewer->isAdmin();
        $canMessage = auth()->id() !== $user->id;

        return view('users.show', compact('user', 'canMessage', 'canSeeCrossStreets'));
    }
}
```

- [ ] **Step 5: Update the profile view to gate cross_streets display**

Open `resources/views/users/show.blade.php` and find where `cross_streets` is rendered. Wrap it:

```blade
@if($canSeeCrossStreets && $user->cross_streets)
    <p class="text-sm text-earth-muted">{{ $user->cross_streets }}</p>
@endif
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --filter ProfilePrivacyTest
```
Expected: 4 tests pass.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php app/Http/Controllers/UserProfileController.php resources/views/users/show.blade.php tests/Feature/ProfilePrivacyTest.php
git commit -m "feat: require auth to view member profiles, hide cross_streets from non-owners"
```

---

## Task 5: Credit Transfer Race Condition Fix

**Files:**
- Modify: `app/Services/CreditService.php`

> **Note:** `SELECT ... FOR UPDATE` requires InnoDB (default on DreamHost MySQL). SQLite (used in unit tests) silently ignores `lockForUpdate()` — the fix is safe to apply and tests will still pass, but the locking behavior itself is only exercised against MySQL.

- [ ] **Step 1: Write a regression test**

Add to `tests/Feature/Requests/CreditTransferTest.php` (create if it doesn't exist):

```php
// tests/Feature/Requests/CreditTransferTest.php
<?php

namespace Tests\Feature\Requests;

use App\Models\ExchangeRequest;
use App\Models\Skill;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTransferTest extends TestCase
{
    use RefreshDatabase;

    private CreditService $creditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creditService = app(CreditService::class);
    }

    public function test_transfer_debits_requester_and_credits_owner(): void
    {
        $requester = User::factory()->create(['time_bank_balance' => 5.0, 'status' => 'active']);
        $owner = User::factory()->create(['time_bank_balance' => 0.0, 'status' => 'active']);

        // No ExchangeRequest factory exists — create directly
        $request = \App\Models\ExchangeRequest::create([
            'requester_id'      => $requester->id,
            'owner_id'          => $owner->id,
            'resource_type'     => 'skill',
            'resource_id'       => 1,
            'proposed_datetime' => now()->addDay(),
            'credit_type'       => 'time_equal',
            'credit_value'      => 2.0,
            'status'            => 'in_progress',
        ]);

        $this->creditService->transfer($request);

        $this->assertEqualsWithDelta(3.0, $requester->fresh()->time_bank_balance, 0.001);
        $this->assertEqualsWithDelta(2.0, $owner->fresh()->time_bank_balance, 0.001);
    }

    public function test_transfer_throws_when_balance_insufficient(): void
    {
        $requester = User::factory()->create(['time_bank_balance' => -4.0, 'status' => 'active']);
        $owner = User::factory()->create(['time_bank_balance' => 0.0, 'status' => 'active']);

        $request = \App\Models\ExchangeRequest::create([
            'requester_id'      => $requester->id,
            'owner_id'          => $owner->id,
            'resource_type'     => 'skill',
            'resource_id'       => 1,
            'proposed_datetime' => now()->addDay(),
            'credit_type'       => 'time_equal',
            'credit_value'      => 2.0,
            'status'            => 'in_progress',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->creditService->transfer($request);
    }

    public function test_gift_transfer_moves_no_credits(): void
    {
        $requester = User::factory()->create(['time_bank_balance' => 0.0, 'status' => 'active']);
        $owner = User::factory()->create(['time_bank_balance' => 0.0, 'status' => 'active']);

        $request = \App\Models\ExchangeRequest::create([
            'requester_id'      => $requester->id,
            'owner_id'          => $owner->id,
            'resource_type'     => 'skill',
            'resource_id'       => 1,
            'proposed_datetime' => now()->addDay(),
            'credit_type'       => 'gift',
            'credit_value'      => 0.0,
            'status'            => 'in_progress',
        ]);

        $this->creditService->transfer($request);

        $this->assertEqualsWithDelta(0.0, $requester->fresh()->time_bank_balance, 0.001);
        $this->assertEqualsWithDelta(0.0, $owner->fresh()->time_bank_balance, 0.001);
    }
}
```

- [ ] **Step 2: Run tests to establish baseline**

```bash
php artisan test --filter CreditTransferTest
```
Expected: All pass (baseline before the change).

- [ ] **Step 3: Apply the lockForUpdate fix in CreditService**

In `app/Services/CreditService.php`, replace the `transfer()` method body:

```php
public function transfer(ExchangeRequest $request): void
{
    if ($request->credit_type === 'gift') {
        return;
    }

    DB::transaction(function () use ($request) {
        $amount = (float) $request->credit_value;

        $balance = DB::table('users')
            ->where('id', $request->requester_id)
            ->lockForUpdate()
            ->value('time_bank_balance');

        if (($balance - $amount) < self::GRACE_THRESHOLD) {
            throw new \RuntimeException('Insufficient balance for credit transfer.');
        }

        Transaction::create([
            'request_id'   => $request->id,
            'from_user_id' => $request->requester_id,
            'to_user_id'   => $request->owner_id,
            'amount'       => $amount,
            'type'         => 'debit',
            'note'         => "Exchange #{$request->id}",
        ]);

        Transaction::create([
            'request_id'   => $request->id,
            'from_user_id' => $request->requester_id,
            'to_user_id'   => $request->owner_id,
            'amount'       => $amount,
            'type'         => 'credit',
            'note'         => "Exchange #{$request->id}",
        ]);

        DB::table('users')->where('id', $request->requester_id)->decrement('time_bank_balance', $amount);
        DB::table('users')->where('id', $request->owner_id)->increment('time_bank_balance', $amount);
    });
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter CreditTransferTest
```
Expected: All pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/CreditService.php tests/Feature/Requests/CreditTransferTest.php
git commit -m "fix: add lockForUpdate to credit transfer to prevent race condition"
```

---

## Task 6: Admin Audit Log — Migration & Model

**Files:**
- Create: `database/migrations/xxxx_create_admin_audit_logs_table.php`
- Create: `app/Models/AdminAuditLog.php`

- [ ] **Step 1: Generate the migration**

```bash
php artisan make:migration create_admin_audit_logs_table
```

- [ ] **Step 2: Write the migration**

Open the generated file and replace the `up()` and `down()` methods:

```php
public function up(): void
{
    Schema::create('admin_audit_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('admin_id')->constrained('users');
        $table->foreignId('target_user_id')->nullable()->constrained('users');
        $table->string('action', 64);
        $table->json('payload');
        $table->string('ip_address', 45);
        $table->timestamp('created_at')->useCurrent();
    });
}

public function down(): void
{
    Schema::dropIfExists('admin_audit_logs');
}
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```
Expected: `admin_audit_logs` table created.

- [ ] **Step 4: Create the model**

```php
// app/Models/AdminAuditLog.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['payload' => 'array'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/AdminAuditLog.php
git commit -m "feat: add admin_audit_logs migration and model"
```

---

## Task 7: Admin Audit Log — Controller, Route & View

**Files:**
- Create: `app/Http/Controllers/Admin/AuditLogController.php`
- Modify: `routes/web.php`
- Create: `resources/views/admin/audit-log/index.blade.php`
- Create: `tests/Feature/Admin/AuditLogTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Admin/AuditLogTest.php
<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_log(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);

        AdminAuditLog::create([
            'admin_id'       => $admin->id,
            'target_user_id' => $member->id,
            'action'         => 'status_change',
            'payload'        => ['before' => 'active', 'after' => 'suspended'],
            'ip_address'     => '127.0.0.1',
        ]);

        $response = $this->actingAs($admin)->get('/admin/audit-log');

        $response->assertOk();
        $response->assertSee('status_change');
    }

    public function test_non_admin_cannot_view_audit_log(): void
    {
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);

        $this->actingAs($member)->get('/admin/audit-log')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

```bash
php artisan test --filter AuditLogTest
```
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Create the controller**

```php
// app/Http/Controllers/Admin/AuditLogController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AdminAuditLog::with(['admin:id,name', 'targetUser:id,name'])
            ->when($request->action, fn($q, $a) => $q->where('action', $a))
            ->when($request->admin_id, fn($q, $id) => $q->where('admin_id', $id))
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $admins = User::where('role', 'admin')->orderBy('name')->get(['id', 'name']);
        $actions = ['status_change', 'credit_adjustment', 'post_create', 'post_delete'];

        return view('admin.audit-log.index', compact('logs', 'admins', 'actions'));
    }
}
```

- [ ] **Step 4: Register the route in routes/web.php**

Inside the admin middleware group, add:

```php
Route::get('/audit-log', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-log.index');
```

- [ ] **Step 5: Create the view**

```blade
{{-- resources/views/admin/audit-log/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-semibold text-earth">Admin Audit Log</h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Filters --}}
        <form method="GET" class="flex gap-3 mb-6">
            <select name="action" class="rounded-lg border-gray-300 text-sm">
                <option value="">All actions</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>
            <select name="admin_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">All admins</option>
                @foreach($admins as $admin)
                    <option value="{{ $admin->id }}" @selected(request('admin_id') == $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-forest text-white rounded-lg text-sm">Filter</button>
            <a href="{{ route('admin.audit-log.index') }}" class="px-4 py-2 text-earth-muted text-sm">Clear</a>
        </form>

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-cream">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">When</th>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">Admin</th>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">Action</th>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">Target</th>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">Detail</th>
                        <th class="px-4 py-3 text-left font-medium text-earth-muted">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-earth-muted whitespace-nowrap">{{ $log->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-earth">{{ $log->admin->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-mint text-forest">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-earth">{{ $log->targetUser->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-earth-muted font-mono text-xs">{{ json_encode($log->payload) }}</td>
                            <td class="px-4 py-3 text-earth-muted text-xs">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-earth-muted">No audit log entries yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --filter AuditLogTest
```
Expected: 2 tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/AuditLogController.php routes/web.php resources/views/admin/audit-log/ tests/Feature/Admin/AuditLogTest.php
git commit -m "feat: add admin audit log controller, route, and view"
```

---

## Task 8: Audit Logging in MemberController

**Files:**
- Modify: `app/Http/Controllers/Admin/MemberController.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/AuditLogTest.php`:

```php
public function test_status_change_is_logged(): void
{
    $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
    $member = User::factory()->create(['status' => 'active', 'role' => 'member']);

    $this->actingAs($admin)
        ->patch("/admin/members/{$member->id}/status", ['status' => 'suspended']);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id'       => $admin->id,
        'target_user_id' => $member->id,
        'action'         => 'status_change',
    ]);

    $log = AdminAuditLog::where('action', 'status_change')->first();
    $this->assertEquals(['before' => 'active', 'after' => 'suspended'], $log->payload);
}

public function test_credit_adjustment_is_logged_with_balance_snapshot(): void
{
    $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
    $member = User::factory()->create(['status' => 'active', 'role' => 'member', 'time_bank_balance' => 5.0]);

    $this->actingAs($admin)
        ->post("/admin/members/{$member->id}/adjust-credits", [
            'amount' => 3.0,
            'note'   => 'Test adjustment',
        ]);

    $log = AdminAuditLog::where('action', 'credit_adjustment')->first();
    $this->assertNotNull($log);
    $this->assertEquals(5.0, $log->payload['balance_before']);
    $this->assertEquals(8.0, $log->payload['balance_after']);
}

public function test_credit_adjustment_rejected_outside_100_limit(): void
{
    $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
    $member = User::factory()->create(['status' => 'active', 'role' => 'member', 'time_bank_balance' => 0.0]);

    $response = $this->actingAs($admin)
        ->post("/admin/members/{$member->id}/adjust-credits", [
            'amount' => 150.0,
            'note'   => 'Too large',
        ]);

    $response->assertSessionHasErrors('amount');
    $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'credit_adjustment']);
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test --filter AuditLogTest
```
Expected: 3 new tests fail.

- [ ] **Step 3: Update MemberController**

```php
// app/Http/Controllers/Admin/MemberController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $members = User::with('referrer:id,name')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('admin.members.index', compact('members'));
    }

    public function updateStatus(Request $request, User $user)
    {
        $request->validate(['status' => 'required|in:active,suspended,pending']);

        $before = $user->status;
        $user->update(['status' => $request->status]);

        AdminAuditLog::create([
            'admin_id'       => $request->user()->id,
            'target_user_id' => $user->id,
            'action'         => 'status_change',
            'payload'        => ['before' => $before, 'after' => $request->status],
            'ip_address'     => $request->ip(),
        ]);

        return back()->with('success', "Member status updated to {$request->status}.");
    }

    public function adjustCredits(Request $request, User $user, CreditService $creditService)
    {
        $request->validate([
            'amount' => 'required|numeric|min:-100|max:100',
            'note'   => 'required|string|max:255',
        ]);

        $balanceBefore = (float) $user->time_bank_balance;
        $creditService->adjust($user->id, (float) $request->amount, $request->note);
        $user->refresh();

        AdminAuditLog::create([
            'admin_id'       => $request->user()->id,
            'target_user_id' => $user->id,
            'action'         => 'credit_adjustment',
            'payload'        => [
                'amount'         => (float) $request->amount,
                'note'           => $request->note,
                'balance_before' => $balanceBefore,
                'balance_after'  => (float) $user->time_bank_balance,
            ],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Credit adjustment applied.');
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter AuditLogTest
```
Expected: All 5 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/MemberController.php tests/Feature/Admin/AuditLogTest.php
git commit -m "feat: log status changes and credit adjustments to admin audit log"
```

---

## Task 9: Audit Logging in PostController

**Files:**
- Modify: `app/Http/Controllers/Admin/PostController.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/AuditLogTest.php`:

```php
public function test_post_create_is_logged(): void
{
    $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);

    $this->actingAs($admin)->post('/admin/posts', [
        'title'     => 'Test Post',
        'body'      => 'Some body content.',
        'published' => false,
    ]);

    $log = AdminAuditLog::where('action', 'post_create')->first();
    $this->assertNotNull($log);
    $this->assertEquals('Test Post', $log->payload['title']);
}

public function test_post_delete_is_logged(): void
{
    $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
    // No Post factory exists — create directly
    $post = \App\Models\Post::create(['user_id' => $admin->id, 'title' => 'Delete Me', 'body' => 'Body content.']);

    $this->actingAs($admin)->delete("/admin/posts/{$post->id}");

    $log = AdminAuditLog::where('action', 'post_delete')->first();
    $this->assertNotNull($log);
    $this->assertEquals('Delete Me', $log->payload['title']);
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test --filter AuditLogTest
```
Expected: 2 new tests fail.

- [ ] **Step 3: Update PostController**

```php
// app/Http/Controllers/Admin/PostController.php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with('user:id,name')->latest()->paginate(20);
        return view('admin.posts.index', compact('posts'));
    }

    public function create()
    {
        return view('admin.posts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'body'      => 'required|string',
            'published' => 'boolean',
        ]);

        $post = Post::create([
            'user_id'      => $request->user()->id,
            'title'        => $data['title'],
            'body'         => $data['body'],
            'published_at' => $request->boolean('published') ? now() : null,
        ]);

        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => 'post_create',
            'payload'    => ['post_id' => $post->id, 'title' => $post->title],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.posts.index')
            ->with('success', 'Post ' . ($request->boolean('published') ? 'published' : 'saved as draft') . '.');
    }

    public function edit(Post $post)
    {
        return view('admin.posts.edit', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'body'      => 'required|string',
            'published' => 'boolean',
        ]);

        $post->update([
            'title'        => $data['title'],
            'body'         => $data['body'],
            'published_at' => $request->boolean('published')
                ? ($post->published_at ?? now())
                : null,
        ]);

        return redirect()->route('admin.posts.index')->with('success', 'Post updated.');
    }

    public function destroy(Request $request, Post $post)
    {
        $title = $post->title;
        $postId = $post->id;
        $post->delete();

        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => 'post_delete',
            'payload'    => ['post_id' => $postId, 'title' => $title],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Post deleted.');
    }
}
```

> **Note:** `destroy()` now accepts `Request $request` as a parameter (needed for `$request->user()` and `$request->ip()`). Laravel's route model binding still works — the `Post $post` parameter is resolved as before.

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter AuditLogTest
```
Expected: All 7 tests pass.

- [ ] **Step 5: Run full test suite to check for regressions**

```bash
php artisan test
```
Expected: All green.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/PostController.php tests/Feature/Admin/AuditLogTest.php
git commit -m "feat: log post create and delete to admin audit log"
```

---

## Task 10: Backup Script & Restore Docs

**Files:**
- Create: `backup.sh`
- Create: `RESTORE.md` (gitignored)
- Modify: `.gitignore`

- [ ] **Step 1: Create backup.sh**

```bash
# backup.sh
#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"
BACKUP_DIR="$HOME/olyhillshub-backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env not found at $ENV_FILE" >&2
    exit 1
fi

get_env() {
    grep -E "^${1}=" "$ENV_FILE" | head -1 | sed "s/^${1}=//" | sed "s/^['\"]//;s/['\"]$//"
}

DB_HOST=$(get_env DB_HOST)
DB_DATABASE=$(get_env DB_DATABASE)
DB_USERNAME=$(get_env DB_USERNAME)
DB_PASSWORD=$(get_env DB_PASSWORD)

if [ -z "$DB_HOST" ] || [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
    echo "ERROR: Missing DB credentials in .env (DB_HOST, DB_DATABASE, DB_USERNAME required)" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"

# Database backup
DB_FILE="$BACKUP_DIR/db_${TIMESTAMP}.sql.gz"
echo "Backing up database..."
mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$DB_FILE"
echo "  ✓ Database: $DB_FILE ($(du -sh "$DB_FILE" | cut -f1))"

# Uploads backup
UPLOADS_SRC="$SCRIPT_DIR/storage/app/public"
if [ -d "$UPLOADS_SRC" ] && [ "$(ls -A "$UPLOADS_SRC" 2>/dev/null)" ]; then
    UPLOADS_DEST="$BACKUP_DIR/uploads_${TIMESTAMP}"
    echo "Backing up uploads..."
    cp -r "$UPLOADS_SRC" "$UPLOADS_DEST"
    echo "  ✓ Uploads: $UPLOADS_DEST ($(du -sh "$UPLOADS_DEST" | cut -f1))"
else
    echo "  — No uploads to back up."
fi

echo "Backup complete. Files in: $BACKUP_DIR"
```

- [ ] **Step 2: Make it executable**

```bash
chmod +x backup.sh
```

- [ ] **Step 3: Test it locally**

```bash
./backup.sh
```
Expected: Prints database and uploads backup paths with sizes. Creates `~/olyhillshub-backups/db_YYYYMMDD_HHMMSS.sql.gz`.

- [ ] **Step 4: Add RESTORE.md to .gitignore**

Open `.gitignore` and add:

```
RESTORE.md
```

- [ ] **Step 5: Create RESTORE.md locally (not committed)**

Create `RESTORE.md` in the project root with your production credentials filled in. Template:

```markdown
# OlyHillsHub Restore Procedure

## Restore Database from Backup

```bash
gunzip < ~/olyhillshub-backups/db_YYYYMMDD_HHMMSS.sql.gz \
  | mysql -h YOUR_DB_HOST -u YOUR_DB_USER -p YOUR_DB_NAME
```

## Restore Uploads

```bash
rsync -av ~/olyhillshub-backups/uploads_YYYYMMDD_HHMMSS/ \
  /path/to/project/storage/app/public/
```

## Post-Restore

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate:status   # verify schema matches
```

## DreamHost Cron (when ready)

Add to DreamHost panel → Cron Jobs:
```
0 2 * * * /path/to/project/backup.sh >> /home/USERNAME/backup.log 2>&1
```

Retention (add to same cron after backup line):
```
find /home/USERNAME/olyhillshub-backups -name "db_*.gz" -mtime +14 -delete
```
```

- [ ] **Step 6: Commit**

```bash
git add backup.sh .gitignore
git commit -m "feat: add backup.sh script and gitignore RESTORE.md"
```

---

## Final Verification

- [ ] **Run the full test suite**

```bash
php artisan test
```
Expected: All green.

- [ ] **Manually verify security headers**

```bash
php artisan serve
curl -I http://localhost:8000/ | grep -E "X-Frame|X-Content|CSP|Referrer"
```
Expected: All four headers present.

- [ ] **Verify config:check in dev vs prod mode**

```bash
php artisan config:check
# Should warn but exit 0 in dev

APP_ENV=production php artisan config:check
# Should exit 1 if APP_DEBUG=true
```

- [ ] **Final commit tag**

```bash
git tag security-hardening-v1
```
