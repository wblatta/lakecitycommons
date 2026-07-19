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
