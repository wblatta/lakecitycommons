# Lake City Commons — Public Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the OlyHillsHub app into the public-facing Lake City Commons site — feature-flagged community features, public news pages, events calendar, organization directory, and community submission form.

**Architecture:** One Laravel app, two faces. Community features (skills, items, requests, messages, referrals) hide behind a `FEATURE_COMMUNITY` flag (off in production). New public routes need no auth: `/news`, `/events`, `/directory`, `/submit`, plus RSS and sitemap. The content pipeline (sources, fetchers, Claude drafting, Buttondown) is **Plan 2** — not in this plan.

**Tech Stack:** Laravel 12 (PHP 8.3), Blade + Tailwind + Alpine (existing palette), MySQL prod / SQLite-memory tests, Spatie MediaLibrary (org logos).

## Global Constraints

- **No new composer or npm packages.** Everything here uses what's installed.
- Site name is exactly **"Lake City Commons"** everywhere user-visible.
- DreamHost shared hosting: `file` cache/session, `database` queue — do not introduce Redis/websockets.
- All tests use `RefreshDatabase`; never hit live external services.
- Existing test suite must stay green. `phpunit.xml` sets `FEATURE_COMMUNITY=true` so existing community tests keep passing; flag-off behavior gets its own tests that set `config(['features.community' => false])`.
- Follow existing patterns: inline `$request->validate()` in controllers, `AdminAuditLog::create()` on admin create/delete/moderation actions, Tailwind classes from the existing palette (`bg-cream`, `text-earth`, `text-forest`, `font-display`, etc.).
- Spec: `docs/superpowers/specs/2026-07-19-lake-city-commons-design.md`.

---

### Task 1: Community feature flag

**Files:**
- Create: `config/features.php`
- Create: `app/Http/Middleware/EnsureFeatureEnabled.php`
- Create: `tests/Feature/FeatureFlagTest.php`
- Modify: `bootstrap/app.php` (middleware alias)
- Modify: `routes/web.php` (wrap community routes)
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `phpunit.xml`, `.env.example`
- Modify: `resources/views/layouts/app.blade.php`, `resources/views/layouts/navigation.blade.php` (nav gating)

**Interfaces:**
- Produces: `config('features.community')` (bool), middleware alias `feature:{name}` which aborts 404 when `config("features.{name}")` is falsy. Later tasks assume community routes 404 when the flag is off.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/FeatureFlagTest.php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'member']);
    }

    public function test_community_routes_return_404_when_flag_off(): void
    {
        config(['features.community' => false]);
        $member = $this->member();

        foreach (['/items', '/skills', '/messages', '/my-requests', '/invite'] as $uri) {
            $this->actingAs($member)->get($uri)->assertNotFound();
        }
    }

    public function test_community_routes_accessible_when_flag_on(): void
    {
        config(['features.community' => true]);
        $member = $this->member();

        $this->actingAs($member)->get('/items')->assertOk();
        $this->actingAs($member)->get('/skills')->assertOk();
    }

    public function test_referral_registration_404_when_flag_off(): void
    {
        config(['features.community' => false]);

        $this->get('/register/any-token-value')->assertNotFound();
    }

    public function test_dashboard_redirects_admin_to_posts_when_flag_off(): void
    {
        config(['features.community' => false]);
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);

        $this->actingAs($admin)->get('/dashboard')
            ->assertRedirect(route('admin.posts.index'));
    }

    public function test_dashboard_404_for_member_when_flag_off(): void
    {
        config(['features.community' => false]);

        $this->actingAs($this->member())->get('/dashboard')->assertNotFound();
    }

    public function test_nav_hides_community_links_when_flag_off(): void
    {
        config(['features.community' => false]);
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);

        $response = $this->actingAs($admin)->get('/profile');
        $response->assertOk();
        $response->assertDontSee('>Skills<', false);
        $response->assertDontSee('>Items<', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FeatureFlagTest`
Expected: FAIL (routes return 200/403 instead of 404; config key missing).

- [ ] **Step 3: Implement**

`config/features.php`:

```php
<?php

return [
    'community' => (bool) env('FEATURE_COMMUNITY', false),
];
```

`app/Http/Middleware/EnsureFeatureEnabled.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(config("features.{$feature}"), 404);

        return $next($request);
    }
}
```

In `bootstrap/app.php`, add to the existing `$middleware->alias([...])` array (alongside `admin` and `referral`):

```php
'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
```

In `routes/web.php`:

1. Change the referral registration group's middleware from `->middleware('referral')` to `->middleware(['feature:community', 'referral'])` (feature first, so an invalid token still yields 404 when off).
2. Inside the `['auth', 'verified']` group, wrap ALL community routes — `users.show`, skills, items, invite, requests (all request routes incl. `my-requests`), messages (all), waitlist — in a nested group:

```php
Route::middleware('feature:community')->group(function () {
    // users.show, skills, items, invite, requests, messages, waitlist routes move here unchanged
});
```

Leave `dashboard` and the three `profile` routes outside the nested group.

In `app/Http/Controllers/DashboardController.php`, at the top of `__invoke` (before any existing logic):

```php
if (! config('features.community')) {
    if ($request->user()->role === 'admin') {
        return redirect()->route('admin.posts.index');
    }
    abort(404);
}
```

(If `__invoke` doesn't already receive `Request $request`, add the parameter and import.)

Nav gating — in `resources/views/layouts/app.blade.php`, wrap the `$navLinks` definition so community links only appear when the flag is on:

```php
@php
    $navLinks = config('features.community') ? [
        ['route' => 'skills.index', 'label' => 'Skills'],
        ['route' => 'items.index',  'label' => 'Items'],
        ['route' => 'requests.index', 'label' => 'Requests'],
    ] : [];
@endphp
```

Then run `grep -n "route('items\.\|route('skills\.\|route('requests\.\|route('messages\.\|route('invite\.\|route('users\." resources/views/layouts/*.blade.php` and wrap every remaining hit (desktop nav extras, mobile nav in `app.blade.php`, and `navigation.blade.php`) in `@if(config('features.community')) ... @endif`. Also wrap the brand link's `route('dashboard')` target: use `config('features.community') ? route('dashboard') : url('/')`.

`phpunit.xml` — add inside the `<php>` block:

```xml
<env name="FEATURE_COMMUNITY" value="true"/>
```

`.env.example` — add:

```
FEATURE_COMMUNITY=false
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=FeatureFlagTest` → PASS. Then the full suite: `php artisan test` → all green (existing tests run with flag on).

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: gate community features behind FEATURE_COMMUNITY flag"
```

---

### Task 2: Rebrand + public layout + static home page

**Files:**
- Create: `resources/views/layouts/public.blade.php`
- Create: `resources/views/home.blade.php`
- Create: `app/Http/Controllers/HomeController.php`
- Create: `tests/Feature/PublicSiteTest.php`
- Modify: `routes/web.php` (`/` route), `config/app.php`, `.env.example`, `README.md`
- Modify: `resources/views/layouts/app.blade.php` (brand name)

**Interfaces:**
- Produces: Blade layout `layouts.public` with `@yield('title')`, `@yield('meta')`, `@yield('content')`, and a public nav (Home, News, Events, Directory, Submit). Tasks 4, 6, 8, 10, 12 extend this layout. Route name `home` → public homepage for everyone (no auth redirect).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/PublicSiteTest.php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Lake City Commons');
    }

    public function test_homepage_renders_for_logged_in_users(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);

        $this->actingAs($admin)->get('/')->assertOk();
    }

    public function test_public_nav_has_section_links(): void
    {
        $response = $this->get('/');

        $response->assertSee('News');
        $response->assertSee('Events');
        $response->assertSee('Directory');
        $response->assertSee('Submit');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PublicSiteTest`
Expected: FAIL — `/` redirects auth users, welcome view lacks "Lake City Commons" / nav links.

- [ ] **Step 3: Implement**

`config/app.php`: change the name default to `'name' => env('APP_NAME', 'Lake City Commons'),`. In `.env.example` set `APP_NAME="Lake City Commons"`. In `resources/views/layouts/app.blade.php` replace the literal brand text `OlyHillsHub` with `{{ config('app.name') }}` (both desktop and mobile brand if present; keep the SVG). In `README.md`, retitle the first heading to `# Lake City Commons` and add one sentence: "Public neighborhood news & community site for Lake City, Seattle. The original OlyHillsHub time-banking/item-sharing platform remains in the codebase behind `FEATURE_COMMUNITY`."

`resources/views/layouts/public.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') — @endif{{ config('app.name') }}</title>
    @yield('meta')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-cream font-sans text-earth antialiased">
    <nav class="flex items-center justify-between px-4 md:px-8 bg-white border-b border-forest-pale/60 sticky top-0 z-40 shadow-sm">
        <a href="{{ url('/') }}" class="font-display text-xl font-semibold text-forest py-4">{{ config('app.name') }}</a>
        <div class="flex items-center gap-1 text-sm font-medium">
            @foreach ([['url' => route('news.index'), 'label' => 'News', 'is' => 'news*'], ['url' => route('events.index'), 'label' => 'Events', 'is' => 'events*'], ['url' => route('directory.index'), 'label' => 'Directory', 'is' => 'directory*'], ['url' => route('submissions.create'), 'label' => 'Submit', 'is' => 'submit*']] as $link)
                <a href="{{ $link['url'] }}"
                   class="px-3 md:px-4 py-5 border-b-2 transition-colors {{ request()->is($link['is']) ? 'border-forest text-forest font-semibold' : 'border-transparent text-earth-muted hover:text-forest hover:border-forest-pale' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
            @auth
                <a href="{{ config('features.community') ? route('dashboard') : route('admin.posts.index') }}" class="px-3 py-5 text-earth-muted hover:text-forest">Account</a>
            @endauth
        </div>
    </nav>

    @if (session('success'))
        <div class="max-w-4xl mx-auto mt-4 px-4"><div class="rounded-lg bg-forest-pale/40 text-forest px-4 py-3 text-sm">{{ session('success') }}</div></div>
    @endif

    <main class="max-w-4xl mx-auto px-4 py-8">
        @yield('content')
    </main>

    <footer class="border-t border-forest-pale/60 bg-white mt-16">
        <div class="max-w-4xl mx-auto px-4 py-8 text-sm text-earth-muted space-y-2">
            <p class="font-display text-forest text-base">{{ config('app.name') }}</p>
            <p>Neighborhood news, events, and organizations for Lake City, Seattle — in one place.</p>
            @if (config('services.buttondown.signup_url'))
                <p><a class="text-forest underline" href="{{ config('services.buttondown.signup_url') }}">Get the weekly digest by email →</a></p>
            @endif
        </div>
    </footer>
</body>
</html>
```

Note: the nav references route names `news.index`, `events.index`, `directory.index`, `submissions.create` that later tasks create. To keep this task shippable on its own, register placeholder routes NOW in `routes/web.php` (later tasks replace the closures with controllers):

```php
// Public site
Route::get('/', HomeController::class)->name('home');
Route::view('/news', 'home')->name('news.index');           // replaced in Task 10
Route::view('/events', 'home')->name('events.index');       // replaced in Task 6
Route::view('/directory', 'home')->name('directory.index'); // replaced in Task 4
Route::view('/submit', 'home')->name('submissions.create'); // replaced in Task 8
```

(Delete the old `/` closure route.)

Add to `config/services.php`:

```php
'buttondown' => [
    'signup_url' => env('BUTTONDOWN_SIGNUP_URL'),
],
```

`app/Http/Controllers/HomeController.php`:

```php
<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function __invoke()
    {
        return view('home');
    }
}
```

`resources/views/home.blade.php` (static for now; Task 12 makes it dynamic):

```blade
@extends('layouts.public')

@section('meta')
    <meta name="description" content="Neighborhood news, events, and organizations for Lake City, Seattle.">
@endsection

@section('content')
    <div class="text-center py-12">
        <h1 class="font-display text-4xl md:text-5xl font-semibold text-forest">Lake City Commons</h1>
        <p class="mt-4 text-lg text-earth-muted max-w-2xl mx-auto">
            One place for Lake City, Seattle — the weekly news digest, a neighborhood events
            calendar, and a directory of the organizations that make this place work.
        </p>
        <div class="mt-8 flex justify-center gap-4">
            <a href="{{ route('news.index') }}" class="px-5 py-2.5 rounded-lg bg-forest text-white font-medium">Read the news</a>
            <a href="{{ route('events.index') }}" class="px-5 py-2.5 rounded-lg border border-forest text-forest font-medium">See events</a>
        </div>
    </div>
@endsection
```

Import `HomeController` at the top of `routes/web.php`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=PublicSiteTest` → PASS. Full suite: `php artisan test` → green (welcome-view assertions, if any exist, may need updating to the new home view).

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: rebrand to Lake City Commons, add public layout and homepage"
```

---

### Task 3: Organizations — model, migration, factory, admin CRUD

**Files:**
- Create: `database/migrations/2026_07_19_000001_create_organizations_table.php`
- Create: `app/Models/Organization.php`
- Create: `database/factories/OrganizationFactory.php`
- Create: `app/Http/Controllers/Admin/OrganizationController.php`
- Create: `resources/views/admin/organizations/index.blade.php`, `create.blade.php`, `edit.blade.php`, `_form.blade.php`
- Create: `tests/Feature/Admin/OrganizationTest.php`
- Modify: `routes/web.php` (admin resource route)

**Interfaces:**
- Produces: `Organization` model — `fillable: name, slug, category, description, website, email, phone, address, is_sponsor, sponsor_tier, active`; `Organization::CATEGORIES = ['community','services','business','government']`; auto-slug on create; media collection `logo` (single file); `events()` hasMany (relation added in Task 5). `Organization::factory()` for tests. Route names `admin.organizations.*`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/OrganizationTest.php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'admin']);
    }

    public function test_admin_can_create_organization(): void
    {
        $this->actingAs($this->admin())->post('/admin/organizations', [
            'name' => 'Lake City Farmers Market',
            'category' => 'community',
            'description' => 'Weekly farmers market.',
            'website' => 'https://example.org',
            'active' => 1,
        ])->assertRedirect(route('admin.organizations.index'));

        $this->assertDatabaseHas('organizations', [
            'name' => 'Lake City Farmers Market',
            'slug' => 'lake-city-farmers-market',
            'category' => 'community',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'organization_create']);
    }

    public function test_invalid_category_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/organizations', [
            'name' => 'X', 'category' => 'nonsense',
        ])->assertSessionHasErrors('category');
    }

    public function test_duplicate_names_get_unique_slugs(): void
    {
        Organization::factory()->create(['name' => 'Book Club']);
        $b = Organization::factory()->create(['name' => 'Book Club']);

        $this->assertEquals('book-club-2', $b->slug);
    }

    public function test_admin_can_update_and_delete(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->put("/admin/organizations/{$org->id}", [
            'name' => $org->name, 'category' => 'business',
            'description' => $org->description, 'active' => 1,
        ])->assertRedirect(route('admin.organizations.index'));
        $this->assertEquals('business', $org->fresh()->category);

        $this->actingAs($admin)->delete("/admin/organizations/{$org->id}");
        $this->assertDatabaseMissing('organizations', ['id' => $org->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'organization_delete']);
    }

    public function test_non_admin_cannot_access(): void
    {
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);

        $this->actingAs($member)->get('/admin/organizations')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=OrganizationTest`
Expected: FAIL — table, model, routes missing.

- [ ] **Step 3: Implement**

Migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category'); // community|services|business|government
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_sponsor')->default(false);
            $table->string('sponsor_tier')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
```

`app/Models/Organization.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Organization extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const CATEGORIES = ['community', 'services', 'business', 'government'];

    protected $fillable = [
        'name', 'slug', 'category', 'description', 'website', 'email',
        'phone', 'address', 'is_sponsor', 'sponsor_tier', 'active',
    ];

    protected function casts(): array
    {
        return ['is_sponsor' => 'boolean', 'active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            if (empty($org->slug)) {
                $base = Str::slug($org->name) ?: 'org';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }
                $org->slug = $slug;
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
```

(The `events()` relation references the `Event` model created in Task 5; PHP only resolves it when called, so this is safe now — Task 5's tests exercise it.)

`database/factories/OrganizationFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'category' => fake()->randomElement(['community', 'services', 'business', 'government']),
            'description' => fake()->paragraph(),
            'website' => fake()->url(),
            'active' => true,
        ];
    }
}
```

`app/Http/Controllers/Admin/OrganizationController.php` (mirrors `Admin\PostController` patterns):

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::orderBy('name')->paginate(30);
        return view('admin.organizations.index', compact('organizations'));
    }

    public function create()
    {
        return view('admin.organizations.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $org = Organization::create($data);
        $this->attachLogo($request, $org);

        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => 'organization_create',
            'payload'    => ['organization_id' => $org->id, 'name' => $org->name],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.organizations.index')->with('success', 'Organization created.');
    }

    public function edit(Organization $organization)
    {
        return view('admin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        $organization->update($this->validated($request));
        $this->attachLogo($request, $organization);

        return redirect()->route('admin.organizations.index')->with('success', 'Organization updated.');
    }

    public function destroy(Request $request, Organization $organization)
    {
        $payload = ['organization_id' => $organization->id, 'name' => $organization->name];
        $organization->delete();

        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => 'organization_delete',
            'payload'    => $payload,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Organization deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'category'     => 'required|in:' . implode(',', Organization::CATEGORIES),
            'description'  => 'nullable|string|max:5000',
            'website'      => 'nullable|url|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:40',
            'address'      => 'nullable|string|max:255',
            'is_sponsor'   => 'boolean',
            'sponsor_tier' => 'nullable|string|max:60',
            'active'       => 'boolean',
            'logo'         => 'nullable|image|max:2048',
        ]);

        $data['is_sponsor'] = $request->boolean('is_sponsor');
        $data['active'] = $request->boolean('active');
        unset($data['logo']);

        return $data;
    }

    private function attachLogo(Request $request, Organization $org): void
    {
        if ($request->hasFile('logo')) {
            $org->addMediaFromRequest('logo')->toMediaCollection('logo');
        }
    }
}
```

Route (inside the existing admin group in `routes/web.php`):

```php
Route::resource('organizations', \App\Http\Controllers\Admin\OrganizationController::class)->except(['show']);
```

Views — `resources/views/admin/organizations/_form.blade.php`:

```blade
@csrf
<div class="space-y-4 max-w-xl">
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $organization->name ?? '')" required />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="category" value="Category" />
        <select id="category" name="category" class="mt-1 block w-full rounded-md border-forest-pale">
            @foreach (\App\Models\Organization::CATEGORIES as $cat)
                <option value="{{ $cat }}" @selected(old('category', $organization->category ?? '') === $cat)>{{ ucfirst($cat) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-forest-pale">{{ old('description', $organization->description ?? '') }}</textarea>
    </div>
    @foreach ([['website', 'Website'], ['email', 'Email'], ['phone', 'Phone'], ['address', 'Address']] as [$field, $label])
        <div>
            <x-input-label :for="$field" :value="$label" />
            <x-text-input :id="$field" :name="$field" class="mt-1 block w-full" :value="old($field, $organization->$field ?? '')" />
            <x-input-error :messages="$errors->get($field)" class="mt-1" />
        </div>
    @endforeach
    <div>
        <x-input-label for="logo" value="Logo (optional)" />
        <input type="file" id="logo" name="logo" accept="image/*" class="mt-1 block w-full text-sm" />
        <x-input-error :messages="$errors->get('logo')" class="mt-1" />
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $organization->active ?? true)) /> Active (shown in directory)</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_sponsor" value="1" @checked(old('is_sponsor', $organization->is_sponsor ?? false)) /> Sponsor</label>
    <div>
        <x-input-label for="sponsor_tier" value="Sponsor tier (optional)" />
        <x-text-input id="sponsor_tier" name="sponsor_tier" class="mt-1 block w-full" :value="old('sponsor_tier', $organization->sponsor_tier ?? '')" />
    </div>
    <x-primary-button>Save</x-primary-button>
</div>
```

`create.blade.php`:

```blade
@extends('layouts.app')
@section('title', 'New Organization')
@section('content')
    <h1 class="font-display text-2xl text-forest mb-6">New Organization</h1>
    <form method="POST" action="{{ route('admin.organizations.store') }}" enctype="multipart/form-data">
        @include('admin.organizations._form')
    </form>
@endsection
```

`edit.blade.php`:

```blade
@extends('layouts.app')
@section('title', 'Edit Organization')
@section('content')
    <h1 class="font-display text-2xl text-forest mb-6">Edit {{ $organization->name }}</h1>
    <form method="POST" action="{{ route('admin.organizations.update', $organization) }}" enctype="multipart/form-data">
        @method('PUT')
        @include('admin.organizations._form')
    </form>
@endsection
```

`index.blade.php`:

```blade
@extends('layouts.app')
@section('title', 'Organizations')
@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="font-display text-2xl text-forest">Organizations</h1>
        <a href="{{ route('admin.organizations.create') }}" class="px-4 py-2 rounded-lg bg-forest text-white text-sm font-medium">Add organization</a>
    </div>
    <table class="w-full text-sm">
        <thead><tr class="text-left text-earth-muted border-b border-forest-pale/60">
            <th class="py-2">Name</th><th>Category</th><th>Sponsor</th><th>Active</th><th></th>
        </tr></thead>
        <tbody>
        @foreach ($organizations as $org)
            <tr class="border-b border-forest-pale/30">
                <td class="py-2 font-medium">{{ $org->name }}</td>
                <td>{{ ucfirst($org->category) }}</td>
                <td>{{ $org->is_sponsor ? ($org->sponsor_tier ?: 'yes') : '—' }}</td>
                <td>{{ $org->active ? 'yes' : 'no' }}</td>
                <td class="text-right">
                    <a class="text-forest underline" href="{{ route('admin.organizations.edit', $org) }}">Edit</a>
                    <form class="inline" method="POST" action="{{ route('admin.organizations.destroy', $org) }}" onsubmit="return confirm('Delete {{ $org->name }}?')">
                        @csrf @method('DELETE')
                        <button class="text-red-700 underline ml-2">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="mt-4">{{ $organizations->links() }}</div>
@endsection
```

(If admin views in this codebase use a different layout/section convention than `@extends('layouts.app')` + `@section('content')`, match whatever `resources/views/admin/posts/index.blade.php` does — check it before writing views.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=OrganizationTest` → PASS. Full suite green.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: organizations model and admin CRUD"
```

---

### Task 4: Public directory page

**Files:**
- Create: `app/Http/Controllers/DirectoryController.php`
- Create: `resources/views/directory/index.blade.php`
- Create: `tests/Feature/DirectoryTest.php`
- Modify: `routes/web.php` (replace placeholder route)

**Interfaces:**
- Consumes: `Organization` (Task 3) — `active` scope-by-column, `CATEGORIES`.
- Produces: route `directory.index` at `/directory`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/DirectoryTest.php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_lists_active_organizations_grouped_by_category(): void
    {
        Organization::factory()->create(['name' => 'Helping Hands', 'category' => 'community']);
        Organization::factory()->create(['name' => 'Corner Bakery', 'category' => 'business']);

        $response = $this->get('/directory');

        $response->assertOk();
        $response->assertSeeInOrder(['Community', 'Helping Hands']);
        $response->assertSeeInOrder(['Business', 'Corner Bakery']);
    }

    public function test_inactive_organizations_hidden(): void
    {
        Organization::factory()->create(['name' => 'Ghost Org', 'active' => false]);

        $this->get('/directory')->assertOk()->assertDontSee('Ghost Org');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DirectoryTest` — FAIL (placeholder route renders home view).

- [ ] **Step 3: Implement**

`app/Http/Controllers/DirectoryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Organization;

class DirectoryController extends Controller
{
    public function index()
    {
        $groups = Organization::where('active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $labels = [
            'community'  => 'Community',
            'services'   => 'Services',
            'business'   => 'Business',
            'government' => 'Government',
        ];

        return view('directory.index', compact('groups', 'labels'));
    }
}
```

`resources/views/directory/index.blade.php`:

```blade
@extends('layouts.public')
@section('title', 'Directory')
@section('meta')
    <meta name="description" content="Directory of Lake City, Seattle organizations, services, and businesses.">
@endsection
@section('content')
    <h1 class="font-display text-3xl text-forest mb-8">Lake City Directory</h1>
    @forelse ($labels as $key => $label)
        @continue(!isset($groups[$key]))
        <section class="mb-10">
            <h2 class="font-display text-xl text-forest border-b border-forest-pale/60 pb-2 mb-4">{{ $label }}</h2>
            <div class="grid md:grid-cols-2 gap-4">
                @foreach ($groups[$key] as $org)
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            @if ($logo = $org->getFirstMediaUrl('logo'))
                                <img src="{{ $logo }}" alt="" class="w-10 h-10 rounded object-cover">
                            @endif
                            <h3 class="font-semibold">{{ $org->name }}</h3>
                        </div>
                        @if ($org->description)<p class="mt-2 text-sm text-earth-muted">{{ $org->description }}</p>@endif
                        <div class="mt-2 text-sm space-x-3">
                            @if ($org->website)<a class="text-forest underline" href="{{ $org->website }}" rel="noopener">Website</a>@endif
                            @if ($org->email)<a class="text-forest underline" href="mailto:{{ $org->email }}">Email</a>@endif
                            @if ($org->phone)<span>{{ $org->phone }}</span>@endif
                        </div>
                        @if ($org->address)<p class="mt-1 text-xs text-earth-muted">{{ $org->address }}</p>@endif
                    </div>
                @endforeach
            </div>
        </section>
    @empty
    @endforelse
    @if ($groups->isEmpty())
        <p class="text-earth-muted">The directory is just getting started — <a class="text-forest underline" href="{{ route('submissions.create') }}">tell us about your organization</a>.</p>
    @endif
@endsection
```

In `routes/web.php` replace the `/directory` placeholder with:

```php
Route::get('/directory', [\App\Http\Controllers\DirectoryController::class, 'index'])->name('directory.index');
```

- [ ] **Step 4: Run tests to verify they pass**

`php artisan test --filter=DirectoryTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: public organization directory"
```

---

### Task 5: Events — model, migration, factory

**Files:**
- Create: `database/migrations/2026_07_19_000002_create_events_table.php`
- Create: `app/Models/Event.php`
- Create: `database/factories/EventFactory.php`
- Create: `tests/Unit/EventTest.php`

**Interfaces:**
- Consumes: `Organization` (Task 3).
- Produces: `Event` model — `fillable: title, description, starts_at, ends_at, location, url, organization_id, submission_id, status`; casts `starts_at`/`ends_at` to datetime; `scopeApproved($q)`; `organization()` belongsTo. Statuses: `pending|approved|rejected`. `Event::factory()` (default `status='approved'`, future `starts_at`). `submission_id` is a plain nullable unsignedBigInteger for now — the FK is added in Task 8 when `submissions` exists. `source_id`/`external_uid` are **Plan 2** columns, not created here.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/EventTest.php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_scope_excludes_pending_and_rejected(): void
    {
        Event::factory()->create(['status' => 'approved']);
        Event::factory()->create(['status' => 'pending']);
        Event::factory()->create(['status' => 'rejected']);

        $this->assertEquals(1, Event::approved()->count());
    }

    public function test_event_belongs_to_organization(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        $this->assertTrue($event->organization->is($org));
        $this->assertTrue($org->events->first()->is($event));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

`php artisan test --filter=EventTest` — FAIL (no table/model).

- [ ] **Step 3: Implement**

Migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('submission_id')->nullable();
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->timestamps();
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
```

`app/Models/Event.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'starts_at', 'ends_at', 'location',
        'url', 'organization_id', 'submission_id', 'status',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
```

`database/factories/EventFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'starts_at' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'location' => fake()->streetAddress(),
            'status' => 'approved',
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

`php artisan test --filter=EventTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: events model, migration, factory"
```

---

### Task 6: Public events calendar — list view, month view, org filter

**Files:**
- Create: `app/Http/Controllers/EventController.php`
- Create: `resources/views/events/index.blade.php`, `resources/views/events/month.blade.php`
- Create: `tests/Feature/EventsPageTest.php`
- Modify: `routes/web.php` (replace placeholder route)

**Interfaces:**
- Consumes: `Event` (Task 5), `Organization` (Task 3).
- Produces: route `events.index` at `/events` (query params: `view=month`, `month=YYYY-MM`, `organization=<slug>`). `EventController` gains an `ics()` method in Task 7.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/EventsPageTest.php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shows_upcoming_approved_events_only(): void
    {
        Event::factory()->create(['title' => 'Upcoming Cleanup', 'starts_at' => now()->addDays(3)]);
        Event::factory()->create(['title' => 'Past Picnic', 'starts_at' => now()->subDays(3)]);
        Event::factory()->create(['title' => 'Sketchy Pending', 'status' => 'pending', 'starts_at' => now()->addDays(3)]);

        $response = $this->get('/events');

        $response->assertOk();
        $response->assertSee('Upcoming Cleanup');
        $response->assertDontSee('Past Picnic');
        $response->assertDontSee('Sketchy Pending');
    }

    public function test_filter_by_organization_slug(): void
    {
        $org = Organization::factory()->create(['name' => 'Garden Club']);
        Event::factory()->create(['title' => 'Seed Swap', 'organization_id' => $org->id, 'starts_at' => now()->addDays(2)]);
        Event::factory()->create(['title' => 'Unrelated Meetup', 'starts_at' => now()->addDays(2)]);

        $response = $this->get('/events?organization=' . $org->slug);

        $response->assertSee('Seed Swap');
        $response->assertDontSee('Unrelated Meetup');
    }

    public function test_month_view_renders_grid_with_events(): void
    {
        $date = now()->addMonth()->startOfMonth()->addDays(9)->setTime(18, 0);
        Event::factory()->create(['title' => 'Trivia Night', 'starts_at' => $date]);

        $response = $this->get('/events?view=month&month=' . $date->format('Y-m'));

        $response->assertOk();
        $response->assertSee('Trivia Night');
        $response->assertSee($date->format('F Y'));
    }

    public function test_invalid_month_param_falls_back_to_current_month(): void
    {
        $this->get('/events?view=month&month=garbage')->assertOk()->assertSee(now()->format('F Y'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

`php artisan test --filter=EventsPageTest` — FAIL.

- [ ] **Step 3: Implement**

`app/Http/Controllers/EventController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $organizations = Organization::where('active', true)->orderBy('name')->get();

        $query = Event::approved()->with('organization');

        if ($slug = $request->query('organization')) {
            $query->whereHas('organization', fn ($q) => $q->where('slug', $slug));
        }

        if ($request->query('view') === 'month') {
            try {
                // Anchor to day 1: parsing 'Y-m' alone would inherit today's
                // day-of-month and can overflow short months.
                $month = Carbon::createFromFormat('Y-m-d', $request->query('month') . '-01')->startOfMonth();
            } catch (\Throwable) {
                $month = now()->startOfMonth();
            }
            $gridStart = $month->copy()->startOfWeek(Carbon::SUNDAY);
            $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

            $eventsByDay = $query->whereBetween('starts_at', [$gridStart, $gridEnd])
                ->orderBy('starts_at')->get()
                ->groupBy(fn ($e) => $e->starts_at->format('Y-m-d'));

            return view('events.month', compact('eventsByDay', 'month', 'gridStart', 'gridEnd', 'organizations'));
        }

        $eventsByDay = $query->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')->limit(100)->get()
            ->groupBy(fn ($e) => $e->starts_at->format('Y-m-d'));

        return view('events.index', compact('eventsByDay', 'organizations'));
    }
}
```

`resources/views/events/index.blade.php`:

```blade
@extends('layouts.public')
@section('title', 'Events')
@section('meta')
    <meta name="description" content="Upcoming community events in Lake City, Seattle.">
@endsection
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="font-display text-3xl text-forest">Events</h1>
        <div class="flex items-center gap-3 text-sm">
            <a class="text-forest underline" href="{{ route('events.index', array_merge(request()->only('organization'), ['view' => 'month'])) }}">Month view</a>
            <a class="text-forest underline" href="{{ route('events.ics') }}">Subscribe (.ics)</a>
        </div>
    </div>

    <form method="GET" class="mb-6">
        <select name="organization" onchange="this.form.submit()" class="rounded-md border-forest-pale text-sm">
            <option value="">All organizations</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->slug }}" @selected(request('organization') === $org->slug)>{{ $org->name }}</option>
            @endforeach
        </select>
    </form>

    @forelse ($eventsByDay as $day => $events)
        <section class="mb-6">
            <h2 class="font-display text-lg text-forest mb-2">{{ \Carbon\Carbon::parse($day)->format('l, F j') }}</h2>
            <div class="space-y-3">
                @foreach ($events as $event)
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="flex items-baseline justify-between gap-4">
                            <h3 class="font-semibold">{{ $event->url ? '' : '' }}@if($event->url)<a class="text-forest" href="{{ $event->url }}" rel="noopener">{{ $event->title }}</a>@else{{ $event->title }}@endif</h3>
                            <span class="text-sm text-earth-muted whitespace-nowrap">{{ $event->starts_at->format('g:i A') }}</span>
                        </div>
                        @if ($event->organization)<p class="text-xs text-earth-muted mt-0.5">{{ $event->organization->name }}</p>@endif
                        @if ($event->location)<p class="text-sm mt-1">{{ $event->location }}</p>@endif
                        @if ($event->description)<p class="text-sm text-earth-muted mt-1">{{ \Illuminate\Support\Str::limit($event->description, 200) }}</p>@endif
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <p class="text-earth-muted">No upcoming events yet — <a class="text-forest underline" href="{{ route('submissions.create') }}">submit one</a>.</p>
    @endforelse
@endsection
```

(Note: `events.ics` route is created in Task 7. To keep this task green on its own, add the ICS placeholder route now in `routes/web.php`: `Route::get('/events.ics', fn () => abort(404))->name('events.ics');` — Task 7 replaces it.)

`resources/views/events/month.blade.php`:

```blade
@extends('layouts.public')
@section('title', 'Events — ' . $month->format('F Y'))
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="font-display text-3xl text-forest">{{ $month->format('F Y') }}</h1>
        <div class="flex items-center gap-3 text-sm">
            <a class="text-forest underline" href="{{ route('events.index', array_merge(request()->only('organization'), ['view' => 'month', 'month' => $month->copy()->subMonth()->format('Y-m')])) }}">← {{ $month->copy()->subMonth()->format('M') }}</a>
            <a class="text-forest underline" href="{{ route('events.index', array_merge(request()->only('organization'), ['view' => 'month', 'month' => $month->copy()->addMonth()->format('Y-m')])) }}">{{ $month->copy()->addMonth()->format('M') }} →</a>
            <a class="text-forest underline" href="{{ route('events.index', request()->only('organization')) }}">List view</a>
        </div>
    </div>

    <div class="grid grid-cols-7 gap-px bg-forest-pale/40 rounded-lg overflow-hidden text-xs">
        @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
            <div class="bg-white p-2 font-semibold text-earth-muted text-center">{{ $dow }}</div>
        @endforeach
        @for ($day = $gridStart->copy(); $day <= $gridEnd; $day->addDay())
            <div class="bg-white p-2 min-h-24 {{ $day->month === $month->month ? '' : 'opacity-40' }}">
                <div class="text-earth-muted">{{ $day->day }}</div>
                @foreach ($eventsByDay->get($day->format('Y-m-d'), collect()) as $event)
                    <div class="mt-1 rounded bg-forest-pale/40 px-1 py-0.5 text-forest truncate" title="{{ $event->title }}">
                        {{ $event->starts_at->format('g:ia') }} {{ $event->title }}
                    </div>
                @endforeach
            </div>
        @endfor
    </div>
@endsection
```

In `routes/web.php` replace the `/events` placeholder with:

```php
Route::get('/events', [\App\Http\Controllers\EventController::class, 'index'])->name('events.index');
Route::get('/events.ics', fn () => abort(404))->name('events.ics'); // replaced in Task 7
```

- [ ] **Step 4: Run tests to verify they pass**

`php artisan test --filter=EventsPageTest` → PASS. Full suite green.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: public events calendar with list and month views"
```

---

### Task 7: ICS calendar export

**Files:**
- Modify: `app/Http/Controllers/EventController.php` (add `ics()` + `icsEscape()`)
- Modify: `routes/web.php` (replace ICS placeholder)
- Create: `tests/Feature/EventsIcsTest.php`

**Interfaces:**
- Consumes: `Event::approved()` (Task 5).
- Produces: `GET /events.ics` (route name `events.ics`) — `text/calendar` download of approved events from yesterday through +90 days.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/EventsIcsTest.php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsIcsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ics_feed_contains_approved_events(): void
    {
        $event = Event::factory()->create([
            'title' => 'Movie Night; Park Edition',
            'starts_at' => now()->addDays(5)->setTime(19, 0),
        ]);
        Event::factory()->create(['title' => 'Hidden Pending', 'status' => 'pending']);

        $response = $this->get('/events.ics');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $content = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('SUMMARY:Movie Night\; Park Edition', $content);
        $this->assertStringContainsString('UID:event-' . $event->id . '@lakecitycommons.org', $content);
        $this->assertStringNotContainsString('Hidden Pending', $content);
    }

    public function test_events_beyond_90_days_excluded(): void
    {
        Event::factory()->create(['title' => 'Far Future Gala', 'starts_at' => now()->addDays(200)]);

        $this->assertStringNotContainsString('Far Future Gala', $this->get('/events.ics')->getContent());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

`php artisan test --filter=EventsIcsTest` — FAIL (placeholder aborts 404).

- [ ] **Step 3: Implement**

Add to `EventController`:

```php
public function ics()
{
    $events = Event::approved()
        ->where('starts_at', '>=', now()->subDay())
        ->where('starts_at', '<=', now()->addDays(90))
        ->orderBy('starts_at')
        ->get();

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Lake City Commons//Events//EN',
        'CALSCALE:GREGORIAN',
        'X-WR-CALNAME:Lake City Commons Events',
    ];

    foreach ($events as $event) {
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:event-' . $event->id . '@lakecitycommons.org';
        $lines[] = 'DTSTAMP:' . $event->updated_at->utc()->format('Ymd\THis\Z');
        $lines[] = 'DTSTART:' . $event->starts_at->utc()->format('Ymd\THis\Z');
        if ($event->ends_at) {
            $lines[] = 'DTEND:' . $event->ends_at->utc()->format('Ymd\THis\Z');
        }
        $lines[] = 'SUMMARY:' . $this->icsEscape($event->title);
        if ($event->location) {
            $lines[] = 'LOCATION:' . $this->icsEscape($event->location);
        }
        if ($event->url) {
            $lines[] = 'URL:' . $event->url;
        }
        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    return response(implode("\r\n", $lines), 200, [
        'Content-Type' => 'text/calendar; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="lake-city-commons.ics"',
    ]);
}

private function icsEscape(string $text): string
{
    return str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $text);
}
```

Replace the placeholder route:

```php
Route::get('/events.ics', [\App\Http\Controllers\EventController::class, 'ics'])->name('events.ics');
```

- [ ] **Step 4: Run tests to verify they pass**

`php artisan test --filter=EventsIcsTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: ICS export for events calendar"
```

---

### Task 8: Community submissions — model, public form, spam controls

**Files:**
- Create: `database/migrations/2026_07_19_000003_create_submissions_table.php` (also adds FK on `events.submission_id`)
- Create: `app/Models/Submission.php`
- Create: `database/factories/SubmissionFactory.php`
- Create: `app/Http/Controllers/SubmissionController.php`
- Create: `resources/views/submissions/create.blade.php`
- Create: `tests/Feature/SubmissionTest.php`
- Modify: `routes/web.php`, `app/Providers/AppServiceProvider.php` (rate limiter)

**Interfaces:**
- Consumes: `layouts.public` (Task 2), `events.submission_id` column (Task 5).
- Produces: `Submission` model — `fillable: type, submitter_name, submitter_email, title, body, event_fields, status, ip_hash`; `event_fields` cast to array; statuses `pending|approved|rejected`; types `event|announcement`. Routes `submissions.create` (GET /submit) and `submissions.store` (POST /submit, throttle `submissions` = 5/day/IP). Task 9's review queue consumes `Submission::where('status','pending')`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SubmissionTest.php

namespace Tests\Feature;

use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function validEventPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'event',
            'submitter_name' => 'Pat Neighbor',
            'submitter_email' => 'pat@example.com',
            'title' => 'Block Party',
            'body' => 'Annual street gathering.',
            'starts_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'location' => '125th & 28th Ave NE',
        ], $overrides);
    }

    public function test_form_renders(): void
    {
        $this->get('/submit')->assertOk()->assertSee('Submit');
    }

    public function test_valid_event_submission_stored_as_pending(): void
    {
        $this->post('/submit', $this->validEventPayload())->assertRedirect();

        $submission = Submission::first();
        $this->assertEquals('pending', $submission->status);
        $this->assertEquals('event', $submission->type);
        $this->assertEquals('125th & 28th Ave NE', $submission->event_fields['location']);
        $this->assertNotEmpty($submission->ip_hash);
    }

    public function test_announcement_needs_no_event_fields(): void
    {
        $this->post('/submit', [
            'type' => 'announcement',
            'submitter_name' => 'Org Person',
            'submitter_email' => 'org@example.com',
            'title' => 'New Food Bank Hours',
            'body' => 'Now open Saturdays.',
        ])->assertRedirect();

        $this->assertNull(Submission::first()->event_fields);
    }

    public function test_event_submission_requires_future_start(): void
    {
        $this->post('/submit', $this->validEventPayload([
            'starts_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasErrors('starts_at');
    }

    public function test_honeypot_filled_drops_submission_silently(): void
    {
        $this->post('/submit', $this->validEventPayload(['website' => 'http://spam.example']))
            ->assertRedirect();

        $this->assertEquals(0, Submission::count());
    }

    public function test_rate_limited_after_five_per_day(): void
    {
        RateLimiter::clear('submissions');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/submit', $this->validEventPayload(['title' => "Event {$i}"]));
        }

        $this->post('/submit', $this->validEventPayload(['title' => 'One Too Many']))
            ->assertStatus(429);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

`php artisan test --filter=SubmissionTest` — FAIL.

- [ ] **Step 3: Implement**

Migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // event|announcement
            $table->string('submitter_name');
            $table->string('submitter_email');
            $table->string('title');
            $table->text('body');
            $table->json('event_fields')->nullable();
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->string('ip_hash', 64);
            $table->timestamps();
            $table->index('status');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreign('submission_id')->references('id')->on('submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
        });
        Schema::dropIfExists('submissions');
    }
};
```

`app/Models/Submission.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'submitter_name', 'submitter_email', 'title', 'body',
        'event_fields', 'status', 'ip_hash',
    ];

    protected function casts(): array
    {
        return ['event_fields' => 'array'];
    }
}
```

`database/factories/SubmissionFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'announcement',
            'submitter_name' => fake()->name(),
            'submitter_email' => fake()->safeEmail(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => 'pending',
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }

    public function event(): static
    {
        return $this->state(fn () => [
            'type' => 'event',
            'event_fields' => [
                'starts_at' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d H:i:s'),
                'location' => fake()->streetAddress(),
                'url' => null,
            ],
        ]);
    }
}
```

Rate limiter — in `app/Providers/AppServiceProvider.php` `boot()` (imports: `Illuminate\Cache\RateLimiting\Limit`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\RateLimiter`), alongside any existing limiters:

```php
RateLimiter::for('submissions', function (Request $request) {
    return Limit::perDay(5)->by($request->ip());
});
```

`app/Http/Controllers/SubmissionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function create()
    {
        return view('submissions.create');
    }

    public function store(Request $request)
    {
        // Honeypot: real users never see or fill this field.
        if ($request->filled('website')) {
            return redirect()->route('submissions.create')
                ->with('success', 'Thanks! Your submission is in the review queue.');
        }

        $data = $request->validate([
            'type'            => 'required|in:event,announcement',
            'submitter_name'  => 'required|string|max:120',
            'submitter_email' => 'required|email|max:255',
            'title'           => 'required|string|max:255',
            'body'            => 'required|string|max:5000',
            'starts_at'       => 'required_if:type,event|nullable|date|after:now',
            'location'        => 'nullable|string|max:255',
            'url'             => 'nullable|url|max:255',
        ]);

        Submission::create([
            'type'            => $data['type'],
            'submitter_name'  => $data['submitter_name'],
            'submitter_email' => $data['submitter_email'],
            'title'           => $data['title'],
            'body'            => $data['body'],
            'event_fields'    => $data['type'] === 'event' ? [
                'starts_at' => $data['starts_at'],
                'location'  => $data['location'] ?? null,
                'url'       => $data['url'] ?? null,
            ] : null,
            'status'          => 'pending',
            'ip_hash'         => hash('sha256', (string) $request->ip()),
        ]);

        return redirect()->route('submissions.create')
            ->with('success', 'Thanks! Your submission is in the review queue.');
    }
}
```

`resources/views/submissions/create.blade.php`:

```blade
@extends('layouts.public')
@section('title', 'Submit')
@section('content')
    <h1 class="font-display text-3xl text-forest mb-2">Submit an event or announcement</h1>
    <p class="text-earth-muted mb-6">Submissions are reviewed before they appear on the site or in the weekly digest.</p>

    <form method="POST" action="{{ route('submissions.store') }}" class="space-y-4 max-w-xl" x-data="{ type: '{{ old('type', 'event') }}' }">
        @csrf
        <div class="hidden" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <div>
            <x-input-label value="What are you submitting?" />
            <select name="type" x-model="type" class="mt-1 block w-full rounded-md border-forest-pale">
                <option value="event">Event</option>
                <option value="announcement">Announcement</option>
            </select>
        </div>
        <div>
            <x-input-label for="submitter_name" value="Your name" />
            <x-text-input id="submitter_name" name="submitter_name" class="mt-1 block w-full" :value="old('submitter_name')" required />
            <x-input-error :messages="$errors->get('submitter_name')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="submitter_email" value="Your email (not published)" />
            <x-text-input id="submitter_email" name="submitter_email" type="email" class="mt-1 block w-full" :value="old('submitter_email')" required />
            <x-input-error :messages="$errors->get('submitter_email')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="title" value="Title" />
            <x-text-input id="title" name="title" class="mt-1 block w-full" :value="old('title')" required />
            <x-input-error :messages="$errors->get('title')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="body" value="Details" />
            <textarea id="body" name="body" rows="5" class="mt-1 block w-full rounded-md border-forest-pale" required>{{ old('body') }}</textarea>
            <x-input-error :messages="$errors->get('body')" class="mt-1" />
        </div>
        <template x-if="type === 'event'">
            <div class="space-y-4">
                <div>
                    <x-input-label for="starts_at" value="Date & time" />
                    <input type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at') }}" class="mt-1 block w-full rounded-md border-forest-pale">
                    <x-input-error :messages="$errors->get('starts_at')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="location" value="Location" />
                    <x-text-input id="location" name="location" class="mt-1 block w-full" :value="old('location')" />
                </div>
                <div>
                    <x-input-label for="url" value="Link for more info (optional)" />
                    <x-text-input id="url" name="url" class="mt-1 block w-full" :value="old('url')" />
                    <x-input-error :messages="$errors->get('url')" class="mt-1" />
                </div>
            </div>
        </template>
        <x-primary-button>Submit for review</x-primary-button>
    </form>
@endsection
```

Routes — replace the `/submit` placeholder:

```php
Route::get('/submit', [\App\Http\Controllers\SubmissionController::class, 'create'])->name('submissions.create');
Route::post('/submit', [\App\Http\Controllers\SubmissionController::class, 'store'])
    ->middleware('throttle:submissions')->name('submissions.store');
```

- [ ] **Step 4: Run tests to verify they pass**

`php artisan test --filter=SubmissionTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: public submission form with honeypot and rate limiting"
```

---

### Task 9: Admin review queue

**Files:**
- Create: `app/Http/Controllers/Admin/ReviewController.php`
- Create: `resources/views/admin/review/index.blade.php`
- Create: `tests/Feature/Admin/ReviewQueueTest.php`
- Modify: `routes/web.php` (admin routes)

**Interfaces:**
- Consumes: `Submission` (Task 8), `Event` (Task 5), `AdminAuditLog` pattern.
- Produces: routes `admin.review.index` (GET /admin/review), `admin.review.submissions.approve|reject` (POST), `admin.review.events.approve|reject` (POST). Approving an event-type submission creates an approved `Event` with `submission_id` set.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/ReviewQueueTest.php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'admin']);
    }

    public function test_queue_lists_pending_items(): void
    {
        Submission::factory()->create(['title' => 'Pending Announcement']);
        Submission::factory()->create(['title' => 'Old Approved', 'status' => 'approved']);
        Event::factory()->create(['title' => 'Pending Scraped Event', 'status' => 'pending']);

        $response = $this->actingAs($this->admin())->get('/admin/review');

        $response->assertOk();
        $response->assertSee('Pending Announcement');
        $response->assertSee('Pending Scraped Event');
        $response->assertDontSee('Old Approved');
    }

    public function test_approving_event_submission_creates_approved_event(): void
    {
        $submission = Submission::factory()->event()->create(['title' => 'Block Party']);

        $this->actingAs($this->admin())
            ->post("/admin/review/submissions/{$submission->id}/approve")
            ->assertRedirect();

        $this->assertEquals('approved', $submission->fresh()->status);
        $this->assertDatabaseHas('events', [
            'title' => 'Block Party',
            'status' => 'approved',
            'submission_id' => $submission->id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'submission_approve']);
    }

    public function test_approving_announcement_does_not_create_event(): void
    {
        $submission = Submission::factory()->create();

        $this->actingAs($this->admin())->post("/admin/review/submissions/{$submission->id}/approve");

        $this->assertEquals('approved', $submission->fresh()->status);
        $this->assertEquals(0, Event::count());
    }

    public function test_rejecting_submission(): void
    {
        $submission = Submission::factory()->create();

        $this->actingAs($this->admin())->post("/admin/review/submissions/{$submission->id}/reject");

        $this->assertEquals('rejected', $submission->fresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'submission_reject']);
    }

    public function test_approve_and_reject_pending_event(): void
    {
        $event = Event::factory()->create(['status' => 'pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/review/events/{$event->id}/approve");
        $this->assertEquals('approved', $event->fresh()->status);

        $event2 = Event::factory()->create(['status' => 'pending']);
        $this->actingAs($admin)->post("/admin/review/events/{$event2->id}/reject");
        $this->assertEquals('rejected', $event2->fresh()->status);
    }

    public function test_already_handled_submission_404s(): void
    {
        $submission = Submission::factory()->create(['status' => 'approved']);

        $this->actingAs($this->admin())
            ->post("/admin/review/submissions/{$submission->id}/approve")
            ->assertNotFound();
    }

    public function test_non_admin_blocked(): void
    {
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);

        $this->actingAs($member)->get('/admin/review')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

`php artisan test --filter=ReviewQueueTest` — FAIL.

- [ ] **Step 3: Implement**

`app/Http/Controllers/Admin/ReviewController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Event;
use App\Models\Submission;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        $submissions = Submission::where('status', 'pending')->latest()->get();
        $pendingEvents = Event::where('status', 'pending')->orderBy('starts_at')->get();

        return view('admin.review.index', compact('submissions', 'pendingEvents'));
    }

    public function approveSubmission(Request $request, Submission $submission)
    {
        abort_unless($submission->status === 'pending', 404);

        if ($submission->type === 'event') {
            Event::create([
                'title'         => $submission->title,
                'description'   => $submission->body,
                'starts_at'     => $submission->event_fields['starts_at'],
                'location'      => $submission->event_fields['location'] ?? null,
                'url'           => $submission->event_fields['url'] ?? null,
                'submission_id' => $submission->id,
                'status'        => 'approved',
            ]);
        }

        $submission->update(['status' => 'approved']);
        $this->log($request, 'submission_approve', ['submission_id' => $submission->id, 'title' => $submission->title]);

        return back()->with('success', 'Submission approved.');
    }

    public function rejectSubmission(Request $request, Submission $submission)
    {
        abort_unless($submission->status === 'pending', 404);

        $submission->update(['status' => 'rejected']);
        $this->log($request, 'submission_reject', ['submission_id' => $submission->id, 'title' => $submission->title]);

        return back()->with('success', 'Submission rejected.');
    }

    public function approveEvent(Request $request, Event $event)
    {
        abort_unless($event->status === 'pending', 404);

        $event->update(['status' => 'approved']);
        $this->log($request, 'event_approve', ['event_id' => $event->id, 'title' => $event->title]);

        return back()->with('success', 'Event approved.');
    }

    public function rejectEvent(Request $request, Event $event)
    {
        abort_unless($event->status === 'pending', 404);

        $event->update(['status' => 'rejected']);
        $this->log($request, 'event_reject', ['event_id' => $event->id, 'title' => $event->title]);

        return back()->with('success', 'Event rejected.');
    }

    private function log(Request $request, string $action, array $payload): void
    {
        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => $action,
            'payload'    => $payload,
            'ip_address' => $request->ip(),
        ]);
    }
}
```

Routes (inside the admin group):

```php
Route::get('/review', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('review.index');
Route::post('/review/submissions/{submission}/approve', [\App\Http\Controllers\Admin\ReviewController::class, 'approveSubmission'])->name('review.submissions.approve');
Route::post('/review/submissions/{submission}/reject', [\App\Http\Controllers\Admin\ReviewController::class, 'rejectSubmission'])->name('review.submissions.reject');
Route::post('/review/events/{event}/approve', [\App\Http\Controllers\Admin\ReviewController::class, 'approveEvent'])->name('review.events.approve');
Route::post('/review/events/{event}/reject', [\App\Http\Controllers\Admin\ReviewController::class, 'rejectEvent'])->name('review.events.reject');
```

`resources/views/admin/review/index.blade.php`:

```blade
@extends('layouts.app')
@section('title', 'Review Queue')
@section('content')
    <h1 class="font-display text-2xl text-forest mb-6">Review Queue</h1>

    <h2 class="font-display text-lg text-forest mb-3">Submissions ({{ $submissions->count() }})</h2>
    @forelse ($submissions as $submission)
        <div class="bg-white rounded-lg p-4 shadow-sm mb-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <span class="text-xs uppercase tracking-wide text-earth-muted">{{ $submission->type }}</span>
                    <h3 class="font-semibold">{{ $submission->title }}</h3>
                    <p class="text-sm text-earth-muted">From {{ $submission->submitter_name }} ({{ $submission->submitter_email }}) · {{ $submission->created_at->diffForHumans() }}</p>
                    <p class="text-sm mt-2 whitespace-pre-line">{{ $submission->body }}</p>
                    @if ($submission->type === 'event' && $submission->event_fields)
                        <p class="text-sm mt-1 text-forest">
                            {{ \Carbon\Carbon::parse($submission->event_fields['starts_at'])->format('D M j, g:i A') }}
                            @if ($submission->event_fields['location'] ?? null) · {{ $submission->event_fields['location'] }}@endif
                        </p>
                    @endif
                </div>
                <div class="flex gap-2 shrink-0">
                    <form method="POST" action="{{ route('admin.review.submissions.approve', $submission) }}">@csrf<x-primary-button>Approve</x-primary-button></form>
                    <form method="POST" action="{{ route('admin.review.submissions.reject', $submission) }}">@csrf<x-danger-button>Reject</x-danger-button></form>
                </div>
            </div>
        </div>
    @empty
        <p class="text-earth-muted mb-6">No pending submissions.</p>
    @endforelse

    <h2 class="font-display text-lg text-forest mt-8 mb-3">Pending events ({{ $pendingEvents->count() }})</h2>
    @forelse ($pendingEvents as $event)
        <div class="bg-white rounded-lg p-4 shadow-sm mb-3 flex items-start justify-between gap-4">
            <div>
                <h3 class="font-semibold">{{ $event->title }}</h3>
                <p class="text-sm text-forest">{{ $event->starts_at->format('D M j, g:i A') }}@if($event->location) · {{ $event->location }}@endif</p>
                @if ($event->description)<p class="text-sm text-earth-muted mt-1">{{ \Illuminate\Support\Str::limit($event->description, 200) }}</p>@endif
            </div>
            <div class="flex gap-2 shrink-0">
                <form method="POST" action="{{ route('admin.review.events.approve', $event) }}">@csrf<x-primary-button>Approve</x-primary-button></form>
                <form method="POST" action="{{ route('admin.review.events.reject', $event) }}">@csrf<x-danger-button>Reject</x-danger-button></form>
            </div>
        </div>
    @empty
        <p class="text-earth-muted">No pending events.</p>
    @endforelse
@endsection
```

- [ ] **Step 4: Run tests to verify they pass**

`php artisan test --filter=ReviewQueueTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: admin review queue for submissions and pending events"
```

---

### Task 10: Posts — slug + status columns, public news pages, admin form update

**Files:**
- Create: `database/migrations/2026_07_19_000004_add_public_fields_to_posts_table.php`
- Create: `database/factories/PostFactory.php`
- Create: `app/Http/Controllers/NewsController.php`
- Create: `resources/views/news/index.blade.php`, `resources/views/news/show.blade.php`
- Create: `tests/Feature/NewsPageTest.php`
- Modify: `app/Models/Post.php`, `app/Http/Controllers/Admin/PostController.php`, `resources/views/admin/posts/create.blade.php`, `edit.blade.php` (status select), `routes/web.php`

**Interfaces:**
- Consumes: existing `Post` model/admin CRUD.
- Produces: `posts.slug` (unique), `posts.status` (`draft|review|published`), `posts.newsletter_sent_at` (nullable — Plan 2 sets it). `Post::scopePublished()` now requires `status='published'`. Auto-slug on create. `Post::factory()` with `published()` state. Routes `news.index` (/news), `news.show` (/news/{post:slug}).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/NewsPageTest.php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_published_posts_only(): void
    {
        Post::factory()->published()->create(['title' => 'Weekly Digest No. 1']);
        Post::factory()->create(['title' => 'Secret Draft', 'status' => 'draft']);

        $response = $this->get('/news');

        $response->assertOk();
        $response->assertSee('Weekly Digest No. 1');
        $response->assertDontSee('Secret Draft');
    }

    public function test_show_renders_published_post_by_slug(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Big Park News']);

        $this->assertEquals('big-park-news', $post->slug);

        $response = $this->get('/news/' . $post->slug);
        $response->assertOk();
        $response->assertSee('Big Park News');
    }

    public function test_draft_post_404s_publicly(): void
    {
        $post = Post::factory()->create(['status' => 'draft']);

        $this->get('/news/' . $post->slug)->assertNotFound();
    }

    public function test_duplicate_titles_get_unique_slugs(): void
    {
        $a = Post::factory()->published()->create(['title' => 'Cleanup Day']);
        $b = Post::factory()->published()->create(['title' => 'Cleanup Day']);

        $this->assertEquals('cleanup-day', $a->slug);
        $this->assertEquals('cleanup-day-2', $b->slug);
    }

    public function test_admin_can_set_status(): void
    {
        $admin = \App\Models\User::factory()->create(['status' => 'active', 'role' => 'admin']);

        $this->actingAs($admin)->post('/admin/posts', [
            'title' => 'Hello Lake City',
            'body' => 'First post.',
            'status' => 'published',
        ])->assertRedirect(route('admin.posts.index'));

        $post = Post::where('title', 'Hello Lake City')->first();
        $this->assertEquals('published', $post->status);
        $this->assertNotNull($post->published_at);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

`php artisan test --filter=NewsPageTest` — FAIL.

- [ ] **Step 3: Implement**

Migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
            $table->string('status')->default('draft')->after('body'); // draft|review|published
            $table->timestamp('newsletter_sent_at')->nullable()->after('published_at');
        });

        DB::table('posts')->whereNotNull('published_at')->update(['status' => 'published']);

        foreach (DB::table('posts')->whereNull('slug')->get() as $post) {
            $base = Str::slug($post->title) ?: 'post';
            $slug = $base;
            $i = 2;
            while (DB::table('posts')->where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            DB::table('posts')->where('id', $post->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['slug', 'status', 'newsletter_sent_at']);
        });
    }
};
```

`app/Models/Post.php` — replace with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'review', 'published'];

    protected $fillable = ['user_id', 'title', 'slug', 'body', 'status', 'published_at', 'newsletter_sent_at'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'newsletter_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            if (empty($post->slug)) {
                $base = Str::slug($post->title) ?: 'post';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }
                $post->slug = $slug;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isPast();
    }
}
```

`database/factories/PostFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['status' => 'active', 'role' => 'admin']),
            'title' => fake()->sentence(5),
            'body' => fake()->paragraphs(3, true),
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);
    }
}
```

`app/Http/Controllers/Admin/PostController.php` — in `store()` change the validation and create call:

```php
$data = $request->validate([
    'title'  => 'required|string|max:255',
    'body'   => 'required|string',
    'status' => 'required|in:' . implode(',', Post::STATUSES),
]);

$post = Post::create([
    'user_id'      => $request->user()->id,
    'title'        => $data['title'],
    'body'         => $data['body'],
    'status'       => $data['status'],
    'published_at' => $data['status'] === 'published' ? now() : null,
]);
```

…and the success message to `'Post ' . ($data['status'] === 'published' ? 'published' : 'saved as ' . $data['status']) . '.'`. In `update()` likewise:

```php
$data = $request->validate([
    'title'  => 'required|string|max:255',
    'body'   => 'required|string',
    'status' => 'required|in:' . implode(',', Post::STATUSES),
]);

$post->update([
    'title'        => $data['title'],
    'body'         => $data['body'],
    'status'       => $data['status'],
    'published_at' => $data['status'] === 'published' ? ($post->published_at ?? now()) : null,
]);
```

In `resources/views/admin/posts/create.blade.php` and `edit.blade.php`, replace the `published` checkbox with a status select (keep surrounding markup style):

```blade
<div>
    <x-input-label for="status" value="Status" />
    <select id="status" name="status" class="mt-1 block w-full rounded-md border-forest-pale">
        @foreach (\App\Models\Post::STATUSES as $status)
            <option value="{{ $status }}" @selected(old('status', $post->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
</div>
```

`app/Http/Controllers/NewsController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;

class NewsController extends Controller
{
    public function index()
    {
        $posts = Post::published()->orderByDesc('published_at')->paginate(10);

        return view('news.index', compact('posts'));
    }

    public function show(Post $post)
    {
        abort_unless($post->isPublished(), 404);

        return view('news.show', compact('post'));
    }
}
```

Routes — replace the `/news` placeholder:

```php
Route::get('/news', [\App\Http\Controllers\NewsController::class, 'index'])->name('news.index');
Route::get('/news/{post:slug}', [\App\Http\Controllers\NewsController::class, 'show'])->name('news.show');
```

`resources/views/news/index.blade.php`:

```blade
@extends('layouts.public')
@section('title', 'News')
@section('meta')
    <meta name="description" content="News and the weekly digest for Lake City, Seattle.">
@endsection
@section('content')
    <h1 class="font-display text-3xl text-forest mb-8">News</h1>
    <div class="space-y-6">
        @forelse ($posts as $post)
            <article class="bg-white rounded-lg p-6 shadow-sm">
                <h2 class="font-display text-xl"><a class="text-forest" href="{{ route('news.show', $post) }}">{{ $post->title }}</a></h2>
                <p class="text-xs text-earth-muted mt-1">{{ $post->published_at->format('F j, Y') }}</p>
                <p class="text-sm text-earth-muted mt-2">{{ \Illuminate\Support\Str::limit(strip_tags($post->body), 240) }}</p>
            </article>
        @empty
            <p class="text-earth-muted">No posts yet — the first weekly digest is coming soon.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $posts->links() }}</div>
@endsection
```

`resources/views/news/show.blade.php`:

```blade
@extends('layouts.public')
@section('title', $post->title)
@section('meta')
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($post->body), 160) }}">
    <meta property="og:title" content="{{ $post->title }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ route('news.show', $post) }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($post->body), 160) }}">
@endsection
@section('content')
    <article class="bg-white rounded-lg p-6 md:p-10 shadow-sm">
        <h1 class="font-display text-3xl text-forest">{{ $post->title }}</h1>
        <p class="text-xs text-earth-muted mt-2">{{ $post->published_at->format('F j, Y') }}</p>
        <div class="prose prose-sm mt-6 max-w-none whitespace-pre-line">{{ $post->body }}</div>
    </article>
    <p class="mt-6"><a class="text-forest underline" href="{{ route('news.index') }}">← All news</a></p>
@endsection
```

Check for existing admin post tests that send `published` boolean (run `grep -rn "'published'" tests/`) and update them to send `status` instead.

- [ ] **Step 4: Run tests to verify they pass**

`php artisan test --filter=NewsPageTest` → PASS. Full suite: `php artisan test` → green (fix any updated admin post tests).

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: post slugs and statuses, public news pages"
```

---

### Task 11: RSS feed + sitemap

**Files:**
- Create: `app/Http/Controllers/FeedController.php`
- Create: `resources/views/feed/rss.blade.php`
- Create: `tests/Feature/FeedTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `Post::published()` (Task 10).
- Produces: `GET /feed` (route `feed`, RSS 2.0, latest 20 published posts), `GET /sitemap.xml` (route `sitemap` — static pages + published posts).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/FeedTest.php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_rss_feed_lists_published_posts(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Digest & Special <Edition>']);
        Post::factory()->create(['title' => 'Hidden Draft', 'status' => 'draft']);

        $response = $this->get('/feed');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');
        $content = $response->getContent();
        $this->assertStringContainsString('<rss version="2.0"', $content);
        $this->assertStringContainsString('Digest &amp; Special &lt;Edition&gt;', $content);
        $this->assertStringContainsString(route('news.show', $post), $content);
        $this->assertStringNotContainsString('Hidden Draft', $content);
    }

    public function test_sitemap_contains_static_pages_and_posts(): void
    {
        $post = Post::factory()->published()->create();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('<urlset', $content);
        foreach (['/', '/news', '/events', '/directory', '/submit'] as $path) {
            $this->assertStringContainsString('<loc>' . url($path) . '</loc>', $content);
        }
        $this->assertStringContainsString('<loc>' . route('news.show', $post) . '</loc>', $content);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

`php artisan test --filter=FeedTest` — FAIL.

- [ ] **Step 3: Implement**

`app/Http/Controllers/FeedController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;

class FeedController extends Controller
{
    public function rss()
    {
        $posts = Post::published()->orderByDesc('published_at')->limit(20)->get();

        return response()
            ->view('feed.rss', compact('posts'))
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    public function sitemap()
    {
        $urls = collect(['/', '/news', '/events', '/directory', '/submit'])
            ->map(fn ($path) => ['loc' => url($path), 'lastmod' => null]);

        $posts = Post::published()->orderByDesc('published_at')->get()
            ->map(fn ($post) => [
                'loc' => route('news.show', $post),
                'lastmod' => $post->updated_at->toAtomString(),
            ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls->concat($posts) as $url) {
            $xml .= '  <url><loc>' . e($url['loc']) . '</loc>'
                . ($url['lastmod'] ? '<lastmod>' . $url['lastmod'] . '</lastmod>' : '')
                . "</url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
```

`resources/views/feed/rss.blade.php`:

```blade
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ config('app.name') }}</title>
    <link>{{ url('/') }}</link>
    <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml"/>
    <description>Neighborhood news, events, and organizations for Lake City, Seattle.</description>
    <language>en-us</language>
    @foreach ($posts as $post)
    <item>
        <title>{{ $post->title }}</title>
        <link>{{ route('news.show', $post) }}</link>
        <guid isPermaLink="true">{{ route('news.show', $post) }}</guid>
        <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
        <description>{{ \Illuminate\Support\Str::limit(strip_tags($post->body), 500) }}</description>
    </item>
    @endforeach
</channel>
</rss>
```

(Blade's `{{ }}` escapes XML entities automatically — that's what the ampersand test asserts.)

Routes:

```php
Route::get('/feed', [\App\Http\Controllers\FeedController::class, 'rss'])->name('feed');
Route::get('/sitemap.xml', [\App\Http\Controllers\FeedController::class, 'sitemap'])->name('sitemap');
```

- [ ] **Step 4: Run tests to verify they pass**

`php artisan test --filter=FeedTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: RSS feed and sitemap"
```

---

### Task 12: Dynamic homepage, admin nav links, final verification

**Files:**
- Modify: `app/Http/Controllers/HomeController.php`, `resources/views/home.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (admin nav: Organizations, Review links)
- Modify: `tests/Feature/PublicSiteTest.php` (add homepage content tests)

**Interfaces:**
- Consumes: `Post::published()` (Task 10), `Event::approved()` (Task 5), `Organization` (Task 3), routes from Tasks 4/6/8.

- [ ] **Step 1: Write the failing test** (add to `PublicSiteTest`)

```php
public function test_homepage_shows_latest_posts_and_upcoming_events(): void
{
    \App\Models\Post::factory()->published()->create(['title' => 'Fresh Digest']);
    \App\Models\Event::factory()->create(['title' => 'Saturday Market', 'starts_at' => now()->addDays(2)]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Fresh Digest');
    $response->assertSee('Saturday Market');
}

public function test_homepage_handles_empty_state(): void
{
    $this->get('/')->assertOk()->assertSee('Lake City Commons');
}
```

- [ ] **Step 2: Run test to verify it fails**

`php artisan test --filter=PublicSiteTest` — FAIL (homepage is static).

- [ ] **Step 3: Implement**

`app/Http/Controllers/HomeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Post;

class HomeController extends Controller
{
    public function __invoke()
    {
        return view('home', [
            'posts' => Post::published()->orderByDesc('published_at')->limit(3)->get(),
            'events' => Event::approved()->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
        ]);
    }
}
```

In `resources/views/home.blade.php`, after the hero `</div>`, add:

```blade
    <div class="grid md:grid-cols-2 gap-8 mt-4">
        <section>
            <h2 class="font-display text-xl text-forest mb-4">Latest news</h2>
            @forelse ($posts as $post)
                <article class="bg-white rounded-lg p-4 shadow-sm mb-3">
                    <h3 class="font-semibold"><a class="text-forest" href="{{ route('news.show', $post) }}">{{ $post->title }}</a></h3>
                    <p class="text-xs text-earth-muted mt-1">{{ $post->published_at->format('F j, Y') }}</p>
                </article>
            @empty
                <p class="text-sm text-earth-muted">First digest coming soon.</p>
            @endforelse
        </section>
        <section>
            <h2 class="font-display text-xl text-forest mb-4">Coming up</h2>
            @forelse ($events as $event)
                <div class="bg-white rounded-lg p-4 shadow-sm mb-3">
                    <h3 class="font-semibold">{{ $event->title }}</h3>
                    <p class="text-xs text-earth-muted mt-1">{{ $event->starts_at->format('D M j, g:i A') }}@if($event->location) · {{ $event->location }}@endif</p>
                </div>
            @empty
                <p class="text-sm text-earth-muted">No events yet — <a class="text-forest underline" href="{{ route('submissions.create') }}">submit one</a>.</p>
            @endforelse
        </section>
    </div>
```

Admin nav — in `resources/views/layouts/app.blade.php`, find where the admin-only links render (the block containing `route('admin.posts.index')`; `grep -n "admin.posts" resources/views/layouts/*.blade.php`) and add adjacent links in the same markup style:

```blade
<a href="{{ route('admin.review.index') }}" class="...same classes as the posts link...">Review</a>
<a href="{{ route('admin.organizations.index') }}" class="...same classes...">Organizations</a>
```

- [ ] **Step 4: Full verification**

Run: `php artisan test` → entire suite green.
Then flag-off smoke check: `FEATURE_COMMUNITY=false php artisan test --filter=FeatureFlagTest` → PASS.
Then boot it: `php artisan serve` + visit `/`, `/news`, `/events`, `/directory`, `/submit`, `/feed`, `/sitemap.xml`, `/events.ics`, and `/admin/review` as admin.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: dynamic homepage and admin nav links"
```

---

## Deferred to Plan 2 (content pipeline)

Sources registry + fetchers (rss/ics/html/dataset), `content_items` + dedupe, `FetchSourcesJob`/`DraftDigestJob` + Claude API drafting, Buttondown publish push + `newsletter_sent_at`, source-health dashboard warnings, `events.source_id`/`external_uid` columns. See spec sections "Content pipeline" and "Admin additions".
