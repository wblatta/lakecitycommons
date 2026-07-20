<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

class FetchOrgLogos extends Command
{
    protected $signature = 'app:fetch-org-logos {--force : refetch even when a logo already exists}';

    protected $description = 'Fetch organization logos from their own websites (og:image, apple-touch-icon, or icon links)';

    private const MIN_DIMENSION = 64;

    public function handle(): int
    {
        $organizations = Organization::where('active', true)->whereNotNull('website')->get();

        foreach ($organizations as $org) {
            if (! $this->option('force') && $org->getFirstMedia('logo')) {
                $this->line("{$org->name}: already has a logo, skipping");
                continue;
            }

            try {
                $found = $this->findLogo($org->website);

                if ($found === null) {
                    $this->warn("{$org->name}: no suitable image found");
                    continue;
                }

                [$bytes, $extension] = $found;
                $tmp = tempnam(sys_get_temp_dir(), 'orglogo') . '.' . $extension;
                file_put_contents($tmp, $bytes);

                $org->clearMediaCollection('logo');
                $org->addMedia($tmp)
                    ->usingFileName(Str::slug($org->name) . '.' . $extension)
                    ->toMediaCollection('logo');

                $this->info("{$org->name}: logo attached");
            } catch (\Throwable $e) {
                $this->warn("{$org->name}: failed ({$e->getMessage()})");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}|null [bytes, extension]
     */
    private function findLogo(string $website): ?array
    {
        $html = Http::timeout(15)->get($website)->throw()->body();
        $crawler = new Crawler($html, $website);

        $selectors = [
            'meta[property="og:image"]' => 'content',
            'link[rel="apple-touch-icon"]' => 'href',
            'link[rel="icon"]' => 'href',
            'link[rel="shortcut icon"]' => 'href',
        ];

        $candidates = [];
        foreach ($selectors as $selector => $attribute) {
            foreach ($crawler->filter($selector) as $node) {
                $value = (new Crawler($node))->attr($attribute);
                if ($value) {
                    $candidates[] = UriResolver::resolve($value, $website);
                }
            }
        }

        foreach (array_values(array_unique($candidates)) as $url) {
            try {
                $response = Http::timeout(15)->get($url);
                if (! $response->successful()) {
                    continue;
                }

                $bytes = $response->body();
                $info = @getimagesizefromstring($bytes);
                if (! $info || min($info[0], $info[1]) < self::MIN_DIMENSION) {
                    continue;
                }

                $extension = match ($info[2]) {
                    IMAGETYPE_PNG => 'png',
                    IMAGETYPE_JPEG => 'jpg',
                    IMAGETYPE_GIF => 'gif',
                    IMAGETYPE_WEBP => 'webp',
                    default => null,
                };
                if ($extension === null) {
                    continue;
                }

                return [$bytes, $extension];
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
