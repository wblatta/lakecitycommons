<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $categories = [
            ['name' => 'Home & Garden', 'type' => 'both'],
            ['name' => 'Education & Tutoring', 'type' => 'skill'],
            ['name' => 'Creative Arts', 'type' => 'both'],
            ['name' => 'Tech Help', 'type' => 'skill'],
            ['name' => 'Health & Wellness', 'type' => 'skill'],
            ['name' => 'Food & Cooking', 'type' => 'both'],
            ['name' => 'Transport & Errands', 'type' => 'skill'],
            ['name' => 'Tools & Equipment', 'type' => 'item'],
            ['name' => 'Books & Media', 'type' => 'item'],
            ['name' => 'Kids & Family', 'type' => 'both'],
            ['name' => 'Pets', 'type' => 'both'],
            ['name' => 'Other', 'type' => 'both'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name']], $cat);
        }

        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@olyhillshub.local'],
            [
                'name' => 'Admin',
                'password' => Hash::make('AdminPass1!'),
                'status' => 'active',
                'role' => 'admin',
                'neighborhood_area' => 'Olympia Hills',
                'time_bank_balance' => 10.0,
                'email_verified_at' => now(),
            ]
        );
    }
}
