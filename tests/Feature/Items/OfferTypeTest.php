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
        \App\Models\Category::create(['name' => 'General', 'type' => 'item']);
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
}
