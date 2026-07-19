<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'announcement',
            'submitter_name' => fake()->name(),
            'submitter_email' => fake()->safeEmail(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => 'pending',
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }

    public function event(): static
    {
        return $this->state(fn () => [
            'type' => 'event',
            'event_fields' => [
                'starts_at' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d H:i:s'),
                'location' => fake()->streetAddress(),
                'url' => null,
            ],
        ]);
    }
}
