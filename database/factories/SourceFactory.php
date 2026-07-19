<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

class SourceFactory extends Factory
{
    protected $model = Source::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Feed',
            'url' => fake()->url(),
            'type' => 'rss',
            'active' => true,
        ];
    }
}
