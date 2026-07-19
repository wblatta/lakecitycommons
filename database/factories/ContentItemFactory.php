<?php

namespace Database\Factories;

use App\Models\ContentItem;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentItemFactory extends Factory
{
    protected $model = ContentItem::class;

    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'url' => fake()->url(),
            'title' => fake()->sentence(6),
            'summary' => fake()->paragraph(),
            'content_hash' => hash('sha256', (string) \Illuminate\Support\Str::uuid()),
            'kind' => 'news',
            'published_at' => now()->subDays(2),
            'fetched_at' => now(),
            'status' => 'new',
        ];
    }
}
