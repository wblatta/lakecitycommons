<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'category' => fake()->randomElement(['community', 'services', 'business', 'government']),
            'description' => fake()->paragraph(),
            'website' => fake()->url(),
            'active' => true,
        ];
    }
}
