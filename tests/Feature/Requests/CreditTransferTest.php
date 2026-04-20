<?php

namespace Tests\Feature\Requests;

use App\Models\ExchangeRequest;
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

        $request = ExchangeRequest::create([
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

        $request = ExchangeRequest::create([
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

        $request = ExchangeRequest::create([
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
