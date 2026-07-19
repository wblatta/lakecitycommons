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
