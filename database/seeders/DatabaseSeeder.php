<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $categories = [
            ['name' => 'Home & Garden',        'type' => 'both'],
            ['name' => 'Education & Tutoring',  'type' => 'skill'],
            ['name' => 'Creative Arts',         'type' => 'both'],
            ['name' => 'Tech Help',             'type' => 'skill'],
            ['name' => 'Health & Wellness',     'type' => 'skill'],
            ['name' => 'Food & Cooking',        'type' => 'both'],
            ['name' => 'Transport & Errands',   'type' => 'skill'],
            ['name' => 'Tools & Equipment',     'type' => 'item'],
            ['name' => 'Books & Media',         'type' => 'item'],
            ['name' => 'Kids & Family',         'type' => 'both'],
            ['name' => 'Pets',                  'type' => 'both'],
            ['name' => 'Other',                 'type' => 'both'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name']], $cat);
        }

        $homeGarden  = Category::where('name', 'Home & Garden')->first();
        $tools       = Category::where('name', 'Tools & Equipment')->first();
        $food        = Category::where('name', 'Food & Cooking')->first();
        $tech        = Category::where('name', 'Tech Help')->first();

        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@olyhillshub.local'],
            [
                'name'              => 'Admin',
                'password'          => Hash::make('AdminPass1!'),
                'status'            => 'active',
                'role'              => 'admin',
                'neighborhood_area' => 'Olympia Hills',
                'cross_streets'     => 'Cedar Rd & Hill St',
                'bio'               => 'OlyHillsHub admin and long-time neighborhood organizer. Happy to help with tech questions.',
                'time_bank_balance' => 10.0,
                'email_verified_at' => now(),
            ]
        );

        // Admin's skill
        Skill::firstOrCreate(
            ['user_id' => $admin->id, 'title' => 'Computer & Phone Help'],
            [
                'description'  => 'Help setting up devices, troubleshooting software, or teaching you how to use apps. Patient and beginner-friendly.',
                'category_id'  => $tech->id,
                'credit_type'  => 'time_equal',
                'is_available' => true,
            ]
        );

        // Second test user — Sam
        $sam = User::firstOrCreate(
            ['email' => 'sam@olyhillshub.local'],
            [
                'name'              => 'Sam Neighbor',
                'password'          => Hash::make('MemberPass1!'),
                'status'            => 'active',
                'role'              => 'member',
                'neighborhood_area' => 'Eastside',
                'cross_streets'     => 'Oak St & Maple Ave',
                'bio'               => 'Passionate about building community through sharing. I love gardening, fixing bikes, and swapping good food.',
                'time_bank_balance' => 5.0,
                'email_verified_at' => now(),
                'referred_by'       => $admin->id,
            ]
        );

        Skill::firstOrCreate(
            ['user_id' => $sam->id, 'title' => 'Vegetable Garden Help'],
            [
                'description'  => 'Help with planting, pruning, composting, and general vegetable garden care. I have 10 years of experience growing food in the Pacific Northwest.',
                'category_id'  => $homeGarden->id,
                'credit_type'  => 'time_equal',
                'is_available' => true,
            ]
        );

        Skill::firstOrCreate(
            ['user_id' => $sam->id, 'title' => 'Basic Bicycle Repair'],
            [
                'description'  => 'Flat tires, brake adjustments, gear tuning, and general tune-ups. Bring your bike to my driveway and I\'ll sort it out.',
                'category_id'  => $homeGarden->id,
                'credit_type'  => 'gift',
                'is_available' => true,
            ]
        );

        Skill::firstOrCreate(
            ['user_id' => $sam->id, 'title' => 'Sourdough Bread Baking'],
            [
                'description'  => 'Hands-on lesson in my kitchen: mixing, shaping, scoring, and baking. You go home with a loaf and your own starter.',
                'category_id'  => $food->id,
                'credit_type'  => 'custom',
                'custom_credit_value' => 2.0,
                'is_available' => true,
            ]
        );

        Item::firstOrCreate(
            ['user_id' => $sam->id, 'title' => 'Extension Ladder (24ft)'],
            [
                'description'  => 'Heavy-duty aluminum 24-foot extension ladder. Great for gutters, roof access, or painting. Available most weekends.',
                'category_id'  => $tools->id,
                'condition'    => 'good',
                'credit_type'  => 'time_equal',
                'is_available' => true,
            ]
        );

        Item::firstOrCreate(
            ['user_id' => $sam->id, 'title' => 'Stand Mixer (KitchenAid)'],
            [
                'description'  => 'KitchenAid Artisan stand mixer with dough hook, whisk, and flat beater attachments. Perfect for bread or pastry projects.',
                'category_id'  => $food->id,
                'condition'    => 'excellent',
                'credit_type'  => 'gift',
                'is_available' => true,
            ]
        );
    }
}
