<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FetchOrgLogosCommandTest extends TestCase
{
    use RefreshDatabase;

    private function pngBytes(int $size): string
    {
        $im = imagecreatetruecolor($size, $size);
        ob_start();
        imagepng($im);
        imagedestroy($im);

        return ob_get_clean();
    }

    public function test_attaches_og_image_logo(): void
    {
        Storage::fake('public');
        Http::fake([
            'org.example/logo.png' => Http::response($this->pngBytes(128)),
            'org.example*' => Http::response('<html><head><meta property="og:image" content="/logo.png"></head><body></body></html>'),
        ]);
        $org = Organization::factory()->create(['website' => 'https://org.example']);

        $this->artisan('app:fetch-org-logos')->assertSuccessful();

        $this->assertNotNull($org->fresh()->getFirstMedia('logo'));
    }

    public function test_skips_images_smaller_than_64px(): void
    {
        Storage::fake('public');
        Http::fake([
            'org.example/favicon.png' => Http::response($this->pngBytes(16)),
            'org.example*' => Http::response('<html><head><link rel="icon" href="/favicon.png"></head><body></body></html>'),
        ]);
        $org = Organization::factory()->create(['website' => 'https://org.example']);

        $this->artisan('app:fetch-org-logos')->assertSuccessful();

        $this->assertNull($org->fresh()->getFirstMedia('logo'));
    }

    public function test_falls_back_to_apple_touch_icon_when_og_image_too_small(): void
    {
        Storage::fake('public');
        Http::fake([
            'org.example/small-og.png' => Http::response($this->pngBytes(32)),
            'org.example/touch.png' => Http::response($this->pngBytes(180)),
            'org.example*' => Http::response('<html><head><meta property="og:image" content="/small-og.png"><link rel="apple-touch-icon" href="/touch.png"></head><body></body></html>'),
        ]);
        $org = Organization::factory()->create(['website' => 'https://org.example']);

        $this->artisan('app:fetch-org-logos')->assertSuccessful();

        $this->assertNotNull($org->fresh()->getFirstMedia('logo'));
    }

    public function test_skips_org_that_already_has_logo(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create(['website' => 'https://org.example']);
        $tmp = tempnam(sys_get_temp_dir(), 'logo') . '.png';
        file_put_contents($tmp, $this->pngBytes(100));
        $org->addMedia($tmp)->toMediaCollection('logo');

        Http::fake();
        $this->artisan('app:fetch-org-logos')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_survives_site_fetch_failure_and_continues(): void
    {
        Storage::fake('public');
        Http::fake([
            'down.example*' => Http::response('', 500),
            'up.example/logo.png' => Http::response($this->pngBytes(128)),
            'up.example*' => Http::response('<html><head><meta property="og:image" content="/logo.png"></head><body></body></html>'),
        ]);
        Organization::factory()->create(['name' => 'Down Org', 'website' => 'https://down.example']);
        $up = Organization::factory()->create(['name' => 'Up Org', 'website' => 'https://up.example']);

        $this->artisan('app:fetch-org-logos')->assertSuccessful();

        $this->assertNotNull($up->fresh()->getFirstMedia('logo'));
    }
}
