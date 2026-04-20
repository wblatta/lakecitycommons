# Item Offer Types (Gift vs. Lend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add explicit Gift vs. Lend offer types to items, with exchange rate selection for lends, system-managed availability during lends, return tracking, and automatic archiving of gifted items.

**Architecture:** Two migrations add `offer_type` + `is_archived` to items and `returned` to request status. `RequestService` grows item-specific side effects (availability toggling, archiving) triggered on state transitions. Two new `FormRequest` classes replace inline validation in `ItemController`. Views get a two-level Alpine.js form (offer type → exchange rate) and a "Mark as Returned" owner action.

**Tech Stack:** Laravel 12, MySQL (enum ALTER via `DB::statement`), Blade + Alpine.js, PHPUnit feature tests with `RefreshDatabase`.

**Spec:** `docs/superpowers/specs/2026-04-20-item-offer-types-design.md`

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `database/migrations/xxxx_add_offer_type_to_items.php` | Create | Add `offer_type`, `is_archived`; migrate existing gift items |
| `database/migrations/xxxx_add_returned_to_requests_status.php` | Create | Add `returned` to status enum |
| `app/Models/Item.php` | Modify | Add `offer_type`, `is_archived` to fillable + casts |
| `app/Services/RequestService.php` | Modify | Allow `completed→returned`; add item side effects on completion and return |
| `app/Http/Requests/StoreItemRequest.php` | Create | Validate `offer_type` + conditional `credit_type` |
| `app/Http/Requests/UpdateItemRequest.php` | Create | Same as StoreItemRequest |
| `app/Http/Controllers/ItemController.php` | Modify | Use FormRequests; gift forces credit_type; toggle blocked during active lend |
| `resources/views/items/create.blade.php` | Modify | Two-level offer type + exchange rate form |
| `resources/views/items/edit.blade.php` | Modify | Same two-level form, pre-populated |
| `resources/views/requests/show.blade.php` | Modify | "Mark as Returned" button for owner on lend items at `completed` |
| `resources/views/items/show.blade.php` | Modify | Show offer type label instead of raw credit_type |
| `resources/views/items/index.blade.php` | Modify | Add archived items section for item owner's view |
| `tests/Feature/Items/OfferTypeTest.php` | Create | All feature tests for this change |

---

## Task 1: Migration — offer_type and is_archived on items

**Files:**
- Create: `database/migrations/xxxx_add_offer_type_to_items.php`
- Modify: `app/Models/Item.php`

- [ ] **Step 1: Generate the migration**

```bash
php artisan make:migration add_offer_type_to_items
```

- [ ] **Step 2: Write the migration**

Open the generated file and replace the `up()` and `down()` bodies:

```php
public function up(): void
{
    Schema::table('items', function (Blueprint $table) {
        $table->enum('offer_type', ['gift', 'lend'])->default('lend')->after('is_available');
        $table->boolean('is_archived')->default(false)->after('offer_type');
    });

    // Migrate existing gift items
    DB::statement("UPDATE items SET offer_type = 'gift' WHERE credit_type = 'gift'");
}

public function down(): void
{
    Schema::table('items', function (Blueprint $table) {
        $table->dropColumn(['offer_type', 'is_archived']);
    });
}
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```

Expected output: migration runs, `offer_type` and `is_archived` columns added.

- [ ] **Step 4: Update Item model**

In `app/Models/Item.php`, update `$fillable` and `casts()`:

```php
protected $fillable = [
    'user_id', 'title', 'description', 'category_id',
    'condition', 'offer_type', 'credit_type', 'custom_credit_value',
    'is_available', 'is_archived', 'slug',
];

protected function casts(): array
{
    return [
        'is_available'       => 'boolean',
        'is_archived'        => 'boolean',
        'custom_credit_value' => 'decimal:2',
    ];
}
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/Item.php
git commit -m "feat: add offer_type and is_archived columns to items"
```

---

## Task 2: Migration — add 'returned' to request status enum

**Files:**
- Create: `database/migrations/xxxx_add_returned_to_requests_status.php`

- [ ] **Step 1: Generate the migration**

```bash
php artisan make:migration add_returned_to_requests_status
```

- [ ] **Step 2: Write the migration**

```php
public function up(): void
{
    DB::statement("ALTER TABLE requests MODIFY status ENUM('pending','accepted','in_progress','completed','declined','cancelled','returned') NOT NULL DEFAULT 'pending'");
}

public function down(): void
{
    DB::statement("ALTER TABLE requests MODIFY status ENUM('pending','accepted','in_progress','completed','declined','cancelled') NOT NULL DEFAULT 'pending'");
}
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: migration runs without error.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: add returned status to exchange requests"
```

---

## Task 3: RequestService — returned transition and item side effects

**Files:**
- Modify: `app/Services/RequestService.php`
- Create: `tests/Feature/Items/OfferTypeTest.php` (first tests only)

The service gains two responsibilities:
1. Allow `completed → returned` (lend items only) in `transition()`
2. Set item availability/archiving as a side effect in both `transition()` and `confirmCompletion()`

- [ ] **Step 1: Write the failing tests**

```php
// tests/Feature/Items/OfferTypeTest.php
<?php

namespace Tests\Feature\Items;

use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\User;
use App\Services\CreditService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferTypeTest extends TestCase
{
    use RefreshDatabase;

    private RequestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RequestService::class);
    }

    private function makeLendRequest(float $balance = 5.0): ExchangeRequest
    {
        $owner = User::factory()->create(['status' => 'active', 'time_bank_balance' => 0.0]);
        $requester = User::factory()->create(['status' => 'active', 'time_bank_balance' => $balance]);
        $item = Item::create([
            'user_id' => $owner->id, 'title' => 'Test Item', 'description' => 'desc',
            'category_id' => \App\Models\Category::first()?->id ?? 1,
            'condition' => 'good', 'offer_type' => 'lend', 'credit_type' => 'gift',
            'is_available' => true,
        ]);
        return ExchangeRequest::create([
            'requester_id' => $requester->id, 'owner_id' => $owner->id,
            'resource_type' => 'item', 'resource_id' => $item->id,
            'proposed_datetime' => now()->addDay(),
            'credit_type' => 'gift', 'credit_value' => 0.0, 'status' => 'completed',
        ]);
    }

    private function makeGiftRequest(): ExchangeRequest
    {
        $owner = User::factory()->create(['status' => 'active']);
        $requester = User::factory()->create(['status' => 'active']);
        $item = Item::create([
            'user_id' => $owner->id, 'title' => 'Gift Item', 'description' => 'desc',
            'category_id' => \App\Models\Category::first()?->id ?? 1,
            'condition' => 'good', 'offer_type' => 'gift', 'credit_type' => 'gift',
            'is_available' => true,
        ]);
        return ExchangeRequest::create([
            'requester_id' => $requester->id, 'owner_id' => $owner->id,
            'resource_type' => 'item', 'resource_id' => $item->id,
            'proposed_datetime' => now()->addDay(),
            'credit_type' => 'gift', 'credit_value' => 0.0, 'status' => 'in_progress',
            'requester_confirmed_at' => now(), 'owner_confirmed_at' => now(),
        ]);
    }

    public function test_lend_item_can_transition_completed_to_returned(): void
    {
        $req = $this->makeLendRequest();
        $owner = User::find($req->owner_id);

        $this->service->transition($req, 'returned', $owner);

        $this->assertEquals('returned', $req->fresh()->status);
    }

    public function test_returned_transition_makes_item_available_again(): void
    {
        $req = $this->makeLendRequest();
        $item = Item::find($req->resource_id);
        $item->update(['is_available' => false]);
        $owner = User::find($req->owner_id);

        $this->service->transition($req, 'returned', $owner);

        $this->assertTrue($item->fresh()->is_available);
    }

    public function test_gift_item_cannot_transition_to_returned(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $requester = User::factory()->create(['status' => 'active']);
        $item = Item::create([
            'user_id' => $owner->id, 'title' => 'Gift', 'description' => 'desc',
            'category_id' => \App\Models\Category::first()?->id ?? 1,
            'condition' => 'good', 'offer_type' => 'gift', 'credit_type' => 'gift',
            'is_available' => false,
        ]);
        $req = ExchangeRequest::create([
            'requester_id' => $requester->id, 'owner_id' => $owner->id,
            'resource_type' => 'item', 'resource_id' => $item->id,
            'proposed_datetime' => now()->addDay(),
            'credit_type' => 'gift', 'credit_value' => 0.0, 'status' => 'completed',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->transition($req, 'returned', $owner);
    }

    public function test_completing_gift_item_archives_it(): void
    {
        $req = $this->makeGiftRequest();
        $owner = User::find($req->owner_id);

        $this->service->confirmCompletion($req, $owner, app(CreditService::class));

        $item = Item::find($req->resource_id);
        $this->assertTrue($item->is_archived);
        $this->assertFalse($item->is_available);
    }

    public function test_completing_lend_item_makes_it_unavailable_but_not_archived(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $requester = User::factory()->create(['status' => 'active']);
        $item = Item::create([
            'user_id' => $owner->id, 'title' => 'Lend Item', 'description' => 'desc',
            'category_id' => \App\Models\Category::first()?->id ?? 1,
            'condition' => 'good', 'offer_type' => 'lend', 'credit_type' => 'gift',
            'is_available' => true,
        ]);
        $req = ExchangeRequest::create([
            'requester_id' => $requester->id, 'owner_id' => $owner->id,
            'resource_type' => 'item', 'resource_id' => $item->id,
            'proposed_datetime' => now()->addDay(),
            'credit_type' => 'gift', 'credit_value' => 0.0, 'status' => 'in_progress',
            'requester_confirmed_at' => now(), 'owner_confirmed_at' => now(),
        ]);

        $this->service->confirmCompletion($req, $owner, app(CreditService::class));

        $item->refresh();
        $this->assertFalse($item->is_available);
        $this->assertFalse($item->is_archived);
    }
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test --filter OfferTypeTest
```

Expected: 5 failures.

- [ ] **Step 3: Update RequestService**

```php
// app/Services/RequestService.php
<?php

namespace App\Services;

use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\User;

class RequestService
{
    const TRANSITIONS = [
        'pending'     => ['accepted', 'declined', 'cancelled'],
        'accepted'    => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed'   => ['returned'],
        'declined'    => [],
        'cancelled'   => [],
        'returned'    => [],
    ];

    public function transition(ExchangeRequest $request, string $newStatus, User $actor): void
    {
        $allowed = self::TRANSITIONS[$request->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            throw new \RuntimeException(
                "Cannot transition from '{$request->status}' to '{$newStatus}'."
            );
        }

        if ($newStatus === 'returned') {
            if ($request->resource_type !== 'item') {
                throw new \RuntimeException('Only item requests can be marked returned.');
            }
            $item = Item::find($request->resource_id);
            if (!$item || $item->offer_type !== 'lend') {
                throw new \RuntimeException('Only lend items can be marked returned.');
            }
            $request->update(['status' => 'returned']);
            $item->update(['is_available' => true]);
            return;
        }

        $request->update(['status' => $newStatus]);
    }

    public function confirmCompletion(ExchangeRequest $request, User $actor, CreditService $creditService): void
    {
        if ($actor->id === $request->requester_id) {
            $request->update(['requester_confirmed_at' => now()]);
        } elseif ($actor->id === $request->owner_id) {
            $request->update(['owner_confirmed_at' => now()]);
        } else {
            throw new \RuntimeException('User is not a party to this request.');
        }

        $request->refresh();

        if ($request->isBothConfirmed() && $request->status !== 'completed') {
            $creditService->transfer($request);
            $request->update(['status' => 'completed', 'completed_at' => now()]);

            if ($request->resource_type === 'item') {
                $item = Item::find($request->resource_id);
                if ($item) {
                    $item->update([
                        'is_available' => false,
                        'is_archived'  => $item->offer_type === 'gift',
                    ]);
                }
            }
        }
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter OfferTypeTest
```

Expected: 5 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/RequestService.php tests/Feature/Items/OfferTypeTest.php
git commit -m "feat: add returned transition and item side effects to RequestService"
```

---

## Task 4: StoreItemRequest and UpdateItemRequest

**Files:**
- Create: `app/Http/Requests/StoreItemRequest.php`
- Create: `app/Http/Requests/UpdateItemRequest.php`

- [ ] **Step 1: Create StoreItemRequest**

```php
// app/Http/Requests/StoreItemRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'               => 'required|string|max:255',
            'description'         => 'required|string|max:2000',
            'category_id'         => 'required|exists:categories,id',
            'condition'           => 'required|in:excellent,good,fair,poor',
            'offer_type'          => 'required|in:gift,lend',
            'credit_type'         => 'required_if:offer_type,lend|nullable|in:gift,time_equal,custom',
            'custom_credit_value' => 'required_if:credit_type,custom|nullable|numeric|min:0',
            'photos'              => 'nullable|array|max:5',
            'photos.*'            => 'image|mimes:jpeg,png,webp|max:5120',
        ];
    }
}
```

- [ ] **Step 2: Create UpdateItemRequest**

```php
// app/Http/Requests/UpdateItemRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'               => 'required|string|max:255',
            'description'         => 'required|string|max:2000',
            'category_id'         => 'required|exists:categories,id',
            'condition'           => 'required|in:excellent,good,fair,poor',
            'offer_type'          => 'required|in:gift,lend',
            'credit_type'         => 'required_if:offer_type,lend|nullable|in:gift,time_equal,custom',
            'custom_credit_value' => 'required_if:credit_type,custom|nullable|numeric|min:0',
            'is_available'        => 'boolean',
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/StoreItemRequest.php app/Http/Requests/UpdateItemRequest.php
git commit -m "feat: add StoreItemRequest and UpdateItemRequest with offer_type validation"
```

---

## Task 5: Update ItemController

**Files:**
- Modify: `app/Http/Controllers/ItemController.php`

Changes:
- `store()`: use `StoreItemRequest`; force `credit_type = 'gift'` when `offer_type = 'gift'`
- `update()`: use `UpdateItemRequest`; same gift force; block availability toggle when active lend is in flight
- `index()`: add `where('is_archived', false)`
- `toggle()`: block toggle when active lend request (`in_progress` or `completed`) exists

- [ ] **Step 1: Add tests for controller behavior**

Add to `tests/Feature/Items/OfferTypeTest.php`:

```php
public function test_store_gift_item_forces_credit_type_to_gift(): void
{
    $user = User::factory()->create(['status' => 'active']);
    $category = \App\Models\Category::first() ?? \App\Models\Category::create(['name' => 'Other', 'type' => 'both', 'slug' => 'other']);

    $response = $this->actingAs($user)->post('/items', [
        'title'       => 'My Lawnmower',
        'description' => 'A lawnmower.',
        'category_id' => $category->id,
        'condition'   => 'good',
        'offer_type'  => 'gift',
        // deliberately omit credit_type — should be forced to 'gift'
    ]);

    $response->assertRedirect();
    $item = Item::where('title', 'My Lawnmower')->first();
    $this->assertNotNull($item);
    $this->assertEquals('gift', $item->offer_type);
    $this->assertEquals('gift', $item->credit_type);
}

public function test_archived_items_excluded_from_browse(): void
{
    $user = User::factory()->create(['status' => 'active']);
    $category = \App\Models\Category::first() ?? \App\Models\Category::create(['name' => 'Other', 'type' => 'both', 'slug' => 'other']);

    Item::create([
        'user_id' => $user->id, 'title' => 'Archived Lawnmower', 'description' => 'desc',
        'category_id' => $category->id, 'condition' => 'good',
        'offer_type' => 'gift', 'credit_type' => 'gift',
        'is_available' => false, 'is_archived' => true,
    ]);

    $response = $this->actingAs($user)->get('/items');
    $response->assertOk();
    $response->assertDontSee('Archived Lawnmower');
}

public function test_toggle_blocked_when_active_lend_in_progress(): void
{
    $owner = User::factory()->create(['status' => 'active']);
    $requester = User::factory()->create(['status' => 'active']);
    $category = \App\Models\Category::first() ?? \App\Models\Category::create(['name' => 'Other', 'type' => 'both', 'slug' => 'other']);

    $item = Item::create([
        'user_id' => $owner->id, 'title' => 'Active Lend', 'description' => 'desc',
        'category_id' => $category->id, 'condition' => 'good',
        'offer_type' => 'lend', 'credit_type' => 'gift', 'is_available' => false,
    ]);
    ExchangeRequest::create([
        'requester_id' => $requester->id, 'owner_id' => $owner->id,
        'resource_type' => 'item', 'resource_id' => $item->id,
        'proposed_datetime' => now()->addDay(),
        'credit_type' => 'gift', 'credit_value' => 0.0, 'status' => 'in_progress',
    ]);

    $response = $this->actingAs($owner)->patch("/items/{$item->slug}/toggle");
    $response->assertRedirect();
    $response->assertSessionHas('error');
    $this->assertFalse($item->fresh()->is_available);
}
```

- [ ] **Step 2: Run to confirm new tests fail**

```bash
php artisan test --filter OfferTypeTest
```

Expected: 3 new failures.

- [ ] **Step 3: Update ItemController**

```php
// app/Http/Controllers/ItemController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Category;
use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistAvailable;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $items = Item::with(['user:id,name,neighborhood_area', 'category'])
            ->where('is_available', true)
            ->where('is_archived', false)
            ->when($request->category, fn($q, $c) => $q->whereHas('category', fn($q2) => $q2->where('slug', $c)))
            ->latest()
            ->paginate(12);

        $categories = Category::whereIn('type', ['item', 'both'])->orderBy('name')->get();

        return view('items.index', compact('items', 'categories'));
    }

    public function create()
    {
        $categories = Category::whereIn('type', ['item', 'both'])->orderBy('name')->get();
        return view('items.create', compact('categories'));
    }

    public function store(StoreItemRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        if ($data['offer_type'] === 'gift') {
            $data['credit_type'] = 'gift';
            $data['custom_credit_value'] = null;
        }

        $item = Item::create($data);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $item->addMedia($photo)->toMediaCollection('photos');
            }
        }

        return redirect()->route('items.show', $item)->with('success', 'Item listed successfully.');
    }

    public function show(Item $item)
    {
        $item->load(['user:id,name,avatar,neighborhood_area,bio', 'category']);

        $onWaitlist = auth()->check()
            ? WaitlistEntry::where('user_id', auth()->id())
                ->where('resource_type', 'item')
                ->where('resource_id', $item->id)
                ->exists()
            : false;

        $waitlistCount = WaitlistEntry::where('resource_type', 'item')
            ->where('resource_id', $item->id)
            ->count();

        return view('items.show', compact('item', 'onWaitlist', 'waitlistCount'));
    }

    public function toggle(Item $item)
    {
        $this->authorize('update', $item);

        $activeLend = ExchangeRequest::where('resource_type', 'item')
            ->where('resource_id', $item->id)
            ->whereIn('status', ['in_progress', 'completed'])
            ->exists();

        if ($activeLend) {
            return back()->with('error', 'This item is currently lent out and cannot be toggled until it is returned.');
        }

        $item->update(['is_available' => !$item->is_available]);

        if ($item->is_available) {
            $entries = WaitlistEntry::where('resource_type', 'item')
                ->where('resource_id', $item->id)
                ->whereNull('notified_at')
                ->with('user')
                ->get();

            foreach ($entries as $entry) {
                $entry->user->notify(new WaitlistAvailable($item->title, route('items.show', $item)));
                $entry->update(['notified_at' => now()]);
            }
        }

        $message = $item->is_available ? 'Item is now available.' : 'Item placed on hold.';
        return back()->with('success', $message);
    }

    public function edit(Item $item)
    {
        $this->authorize('update', $item);
        $categories = Category::whereIn('type', ['item', 'both'])->orderBy('name')->get();
        return view('items.edit', compact('item', 'categories'));
    }

    public function update(UpdateItemRequest $request, Item $item)
    {
        $this->authorize('update', $item);

        $data = $request->validated();

        if ($data['offer_type'] === 'gift') {
            $data['credit_type'] = 'gift';
            $data['custom_credit_value'] = null;
        }

        $item->update($data);

        return redirect()->route('items.show', $item)->with('success', 'Item updated.');
    }

    public function destroy(Item $item)
    {
        $this->authorize('delete', $item);
        $item->clearMediaCollection('photos');
        $item->delete();
        return redirect()->route('items.index')->with('success', 'Item removed.');
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter OfferTypeTest
```

Expected: all 8 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ItemController.php tests/Feature/Items/OfferTypeTest.php
git commit -m "feat: update ItemController for offer_type, archived browse filter, toggle guard"
```

---

## Task 6: Update item create and edit views

**Files:**
- Modify: `resources/views/items/create.blade.php`
- Modify: `resources/views/items/edit.blade.php`

Replace the existing `Credit Type` section with a two-level Alpine form.

- [ ] **Step 1: Update create.blade.php**

Find and replace the entire `<div x-data="{ creditType: ... }">` block (the Credit Type section) with:

```blade
<div x-data="{ offerType: '{{ old('offer_type', 'lend') }}', creditType: '{{ old('credit_type', 'gift') }}' }">
    <label class="block text-sm font-medium text-earth mb-2">How are you offering this?</label>
    <div class="grid grid-cols-2 gap-3 mb-4">
        <label class="cursor-pointer">
            <input type="radio" name="offer_type" value="lend" x-model="offerType" class="sr-only">
            <div :class="offerType === 'lend' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                 class="border-2 rounded-lg p-4 transition-colors">
                <p class="font-semibold text-sm">Lend</p>
                <p class="text-xs mt-0.5 opacity-70">I want it back</p>
            </div>
        </label>
        <label class="cursor-pointer">
            <input type="radio" name="offer_type" value="gift" x-model="offerType" class="sr-only">
            <div :class="offerType === 'gift' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                 class="border-2 rounded-lg p-4 transition-colors">
                <p class="font-semibold text-sm">Gift</p>
                <p class="text-xs mt-0.5 opacity-70">Keep it, it's yours</p>
            </div>
        </label>
    </div>

    <div x-show="offerType === 'lend'" x-cloak>
        <label class="block text-sm font-medium text-earth mb-2">Exchange rate</label>
        <div class="grid grid-cols-3 gap-3">
            <label class="cursor-pointer">
                <input type="radio" name="credit_type" value="gift" x-model="creditType" class="sr-only">
                <div :class="creditType === 'gift' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                     class="border-2 rounded-lg p-3 text-center text-sm font-medium transition-colors">Free</div>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="credit_type" value="time_equal" x-model="creditType" class="sr-only">
                <div :class="creditType === 'time_equal' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                     class="border-2 rounded-lg p-3 text-center text-sm font-medium transition-colors">Time</div>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="credit_type" value="custom" x-model="creditType" class="sr-only">
                <div :class="creditType === 'custom' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                     class="border-2 rounded-lg p-3 text-center text-sm font-medium transition-colors">Custom</div>
            </label>
        </div>
        <div x-show="creditType === 'custom'" x-cloak class="mt-3">
            <input type="number" name="custom_credit_value" step="0.25" min="0" placeholder="Hours"
                   value="{{ old('custom_credit_value') }}"
                   class="w-40 px-4 py-3 rounded-lg border border-forest-pale focus:outline-none focus:ring-2 focus:ring-forest">
        </div>
    </div>
    @error('offer_type')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    @error('credit_type')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
</div>
```

- [ ] **Step 2: Update edit.blade.php**

Find and replace the `<div x-data="{ creditType: ... }">` block with the same two-level form, using the item's existing values as defaults:

```blade
<div x-data="{ offerType: '{{ old('offer_type', $item->offer_type) }}', creditType: '{{ old('credit_type', $item->credit_type) }}' }">
    <label class="block text-sm font-medium text-earth mb-2">How are you offering this?</label>
    <div class="grid grid-cols-2 gap-3 mb-4">
        <label class="cursor-pointer">
            <input type="radio" name="offer_type" value="lend" x-model="offerType" class="sr-only">
            <div :class="offerType === 'lend' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                 class="border-2 rounded-lg p-4 transition-colors">
                <p class="font-semibold text-sm">Lend</p>
                <p class="text-xs mt-0.5 opacity-70">I want it back</p>
            </div>
        </label>
        <label class="cursor-pointer">
            <input type="radio" name="offer_type" value="gift" x-model="offerType" class="sr-only">
            <div :class="offerType === 'gift' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                 class="border-2 rounded-lg p-4 transition-colors">
                <p class="font-semibold text-sm">Gift</p>
                <p class="text-xs mt-0.5 opacity-70">Keep it, it's yours</p>
            </div>
        </label>
    </div>

    <div x-show="offerType === 'lend'" x-cloak>
        <label class="field-label mb-2">Exchange rate</label>
        <div class="grid grid-cols-3 gap-3">
            <label class="cursor-pointer">
                <input type="radio" name="credit_type" value="gift" x-model="creditType" class="sr-only">
                <div :class="creditType === 'gift' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                     class="border-2 rounded-lg p-3 text-center text-sm font-medium transition-colors">Free</div>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="credit_type" value="time_equal" x-model="creditType" class="sr-only">
                <div :class="creditType === 'time_equal' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                     class="border-2 rounded-lg p-3 text-center text-sm font-medium transition-colors">Time</div>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="credit_type" value="custom" x-model="creditType" class="sr-only">
                <div :class="creditType === 'custom' ? 'border-forest bg-forest-pale text-forest' : 'border-forest-pale text-earth-muted'"
                     class="border-2 rounded-lg p-3 text-center text-sm font-medium transition-colors">Custom</div>
            </label>
        </div>
        <div x-show="creditType === 'custom'" x-cloak class="mt-3">
            <input type="number" name="custom_credit_value"
                   value="{{ old('custom_credit_value', $item->custom_credit_value) }}"
                   step="0.25" min="0" class="w-40 field" placeholder="Hours">
        </div>
    </div>
    @error('offer_type')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    @error('credit_type')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
</div>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/items/create.blade.php resources/views/items/edit.blade.php
git commit -m "feat: two-level offer type / exchange rate form for items"
```

---

## Task 7: "Mark as Returned" button on request show view

**Files:**
- Modify: `resources/views/requests/show.blade.php`
- Modify: `resources/views/items/show.blade.php`

- [ ] **Step 1: Add test**

Add to `tests/Feature/Items/OfferTypeTest.php`:

```php
public function test_owner_sees_mark_as_returned_button_for_lend_item_at_completed(): void
{
    $owner = User::factory()->create(['status' => 'active']);
    $requester = User::factory()->create(['status' => 'active']);
    $category = \App\Models\Category::first() ?? \App\Models\Category::create(['name' => 'Other', 'type' => 'both', 'slug' => 'other']);

    $item = Item::create([
        'user_id' => $owner->id, 'title' => 'Lend Ladder', 'description' => 'desc',
        'category_id' => $category->id, 'condition' => 'good',
        'offer_type' => 'lend', 'credit_type' => 'gift', 'is_available' => false,
    ]);
    $req = ExchangeRequest::create([
        'requester_id' => $requester->id, 'owner_id' => $owner->id,
        'resource_type' => 'item', 'resource_id' => $item->id,
        'proposed_datetime' => now()->addDay(),
        'credit_type' => 'gift', 'credit_value' => 0.0, 'status' => 'completed',
    ]);

    $response = $this->actingAs($owner)->get("/requests/{$req->id}");
    $response->assertOk();
    $response->assertSee('Mark as Returned');
}

public function test_requester_does_not_see_mark_as_returned_button(): void
{
    $owner = User::factory()->create(['status' => 'active']);
    $requester = User::factory()->create(['status' => 'active']);
    $category = \App\Models\Category::first() ?? \App\Models\Category::create(['name' => 'Other', 'type' => 'both', 'slug' => 'other']);

    $item = Item::create([
        'user_id' => $owner->id, 'title' => 'Lend Ladder', 'description' => 'desc',
        'category_id' => $category->id, 'condition' => 'good',
        'offer_type' => 'lend', 'credit_type' => 'gift', 'is_available' => false,
    ]);
    $req = ExchangeRequest::create([
        'requester_id' => $requester->id, 'owner_id' => $owner->id,
        'resource_type' => 'item', 'resource_id' => $item->id,
        'proposed_datetime' => now()->addDay(),
        'credit_type' => 'gift', 'credit_value' => 0.0, 'status' => 'completed',
    ]);

    $response = $this->actingAs($requester)->get("/requests/{$req->id}");
    $response->assertOk();
    $response->assertDontSee('Mark as Returned');
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test --filter OfferTypeTest
```

Expected: 2 new failures.

- [ ] **Step 3: Update requests/show.blade.php**

In `resources/views/requests/show.blade.php`, add the `returned` color to `$statusColors` and add the "Mark as Returned" button block after the existing `@if` action blocks:

First, update `$statusColors`:

```blade
$statusColors = ['pending'=>'bg-amber text-white','accepted'=>'bg-forest-light text-white','in_progress'=>'bg-forest text-white','completed'=>'bg-earth text-white','returned'=>'bg-gray-200 text-gray-600','declined'=>'bg-red-100 text-red-700','cancelled'=>'bg-gray-100 text-gray-500'];
```

Then, inside the `<div class="flex flex-wrap gap-3">` actions block, add after the cancel form:

```blade
@if($isOwner && $req->status === 'completed' && $req->resource_type === 'item')
    @php $lendItem = \App\Models\Item::find($req->resource_id); @endphp
    @if($lendItem && $lendItem->offer_type === 'lend')
        <form method="POST" action="{{ route('requests.transition', $req) }}" class="inline">
            @csrf <input type="hidden" name="status" value="returned">
            <button class="px-4 py-2 bg-forest-pale text-forest text-sm font-semibold rounded-lg hover:bg-forest hover:text-white transition-colors">
                Mark as Returned
            </button>
        </form>
    @endif
@endif
```

- [ ] **Step 4: Update items/show.blade.php to show offer type label**

Find the `@if($item->credit_type === 'gift') Free (gift)` block (around line 65) and replace it:

```blade
@if($item->offer_type === 'gift')
    Gift (free, keep it)
@elseif($item->credit_type === 'gift')
    Free to borrow
@elseif($item->credit_type === 'time_equal')
    1 hour = 1 credit
@else
    {{ number_format($item->custom_credit_value, 1) }} credits
@endif
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter OfferTypeTest
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add resources/views/requests/show.blade.php resources/views/items/show.blade.php tests/Feature/Items/OfferTypeTest.php
git commit -m "feat: add Mark as Returned button and offer type labels to views"
```

---

## Task 8: Archived items in owner's item list

**Files:**
- Modify: `resources/views/items/index.blade.php`
- Modify: `app/Http/Controllers/ItemController.php` (index method)

The browse index already excludes archived items (Task 5). This task adds a separate "Your Archived Items" section visible only to the authenticated user for their own archived items.

- [ ] **Step 1: Add test**

Add to `tests/Feature/Items/OfferTypeTest.php`:

```php
public function test_owner_sees_their_archived_items_in_browse(): void
{
    $owner = User::factory()->create(['status' => 'active']);
    $category = \App\Models\Category::first() ?? \App\Models\Category::create(['name' => 'Other', 'type' => 'both', 'slug' => 'other']);

    Item::create([
        'user_id' => $owner->id, 'title' => 'My Old Lawnmower', 'description' => 'desc',
        'category_id' => $category->id, 'condition' => 'good',
        'offer_type' => 'gift', 'credit_type' => 'gift',
        'is_available' => false, 'is_archived' => true,
    ]);

    $response = $this->actingAs($owner)->get('/items');
    $response->assertOk();
    $response->assertSee('My Old Lawnmower');
    $response->assertSee('Archived');
}

public function test_other_user_cannot_see_archived_items(): void
{
    $owner = User::factory()->create(['status' => 'active']);
    $other = User::factory()->create(['status' => 'active']);
    $category = \App\Models\Category::first() ?? \App\Models\Category::create(['name' => 'Other', 'type' => 'both', 'slug' => 'other']);

    Item::create([
        'user_id' => $owner->id, 'title' => 'My Old Lawnmower', 'description' => 'desc',
        'category_id' => $category->id, 'condition' => 'good',
        'offer_type' => 'gift', 'credit_type' => 'gift',
        'is_available' => false, 'is_archived' => true,
    ]);

    $response = $this->actingAs($other)->get('/items');
    $response->assertOk();
    $response->assertDontSee('My Old Lawnmower');
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test --filter OfferTypeTest
```

Expected: 2 new failures.

- [ ] **Step 3: Pass archivedItems to the view in ItemController::index**

In `app/Http/Controllers/ItemController.php`, update the `index()` method:

```php
public function index(Request $request)
{
    $items = Item::with(['user:id,name,neighborhood_area', 'category'])
        ->where('is_available', true)
        ->where('is_archived', false)
        ->when($request->category, fn($q, $c) => $q->whereHas('category', fn($q2) => $q2->where('slug', $c)))
        ->latest()
        ->paginate(12);

    $categories = Category::whereIn('type', ['item', 'both'])->orderBy('name')->get();

    $archivedItems = auth()->check()
        ? Item::with('category')
            ->where('user_id', auth()->id())
            ->where('is_archived', true)
            ->latest()
            ->get()
        : collect();

    return view('items.index', compact('items', 'categories', 'archivedItems'));
}
```

- [ ] **Step 4: Add archived section to items/index.blade.php**

At the bottom of the view, before the closing `</div>` of the outer container, add:

```blade
@if($archivedItems->isNotEmpty())
    <div class="mt-12">
        <h2 class="font-display text-lg font-semibold text-earth-muted mb-4">Your Archived Items</h2>
        <div class="space-y-3">
            @foreach($archivedItems as $item)
                <div class="bg-white rounded-card shadow-sm border border-gray-100 p-4 flex items-center justify-between gap-4 opacity-60">
                    <div>
                        <p class="font-medium text-earth text-sm">{{ $item->title }}</p>
                        <p class="text-xs text-earth-muted mt-0.5">{{ $item->category->name }} &middot; Gifted {{ $item->updated_at->diffForHumans() }}</p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">Archived</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
```

- [ ] **Step 5: Run all tests**

```bash
php artisan test --filter OfferTypeTest
```

Expected: all tests pass.

- [ ] **Step 6: Run full suite**

```bash
php artisan test
```

Expected: same 4 pre-existing failures, no new failures.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ItemController.php resources/views/items/index.blade.php tests/Feature/Items/OfferTypeTest.php
git commit -m "feat: show owner's archived items in item browse view"
```

---

## Final Verification

- [ ] Start the dev server: `php artisan serve`
- [ ] Log in as admin, create a Lend item with "Free" exchange rate — confirm form works
- [ ] Create a Gift item — confirm exchange rate step is hidden, item saved with `offer_type=gift`
- [ ] Browse `/items` — confirm gifted+archived items are hidden from general browse
- [ ] Log in as admin, view own `/items` — confirm archived items appear at bottom
- [ ] (Optional manual flow) Complete a lend exchange → verify "Mark as Returned" appears → click it → verify item shows as available again
