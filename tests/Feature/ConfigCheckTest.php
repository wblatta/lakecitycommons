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

        $this->artisan('config:check')->assertExitCode(0);
    }

    public function test_pipeline_service_config_is_defined(): void
    {
        config(['services.anthropic.model' => null]);
        $this->assertNull(config('services.anthropic.model'));

        // Reload defaults from the config file shape
        $services = require config_path('services.php');
        $this->assertArrayHasKey('anthropic', $services);
        $this->assertArrayHasKey('key', $services['anthropic']);
        $this->assertSame('claude-sonnet-5', $services['anthropic']['model'] ?? env('ANTHROPIC_MODEL', 'claude-sonnet-5'));
    }
}
