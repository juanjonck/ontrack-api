<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateTestUser extends Command
{
    protected $signature = 'user:create-test';
    protected $description = 'Create a test user with predefined token for development';

    public function handle()
    {
        // Create or update test user
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'currency' => 'USD',
                'currency_symbol' => '$',
                'currency_flag' => '🇺🇸',
                'onboarding_completed' => true,
            ]
        );

        // Delete existing tokens for this user
        $user->tokens()->delete();

        // Create a specific token - Sanctum tokens have hashed values
        $token = $user->createToken('test-app');

        // We need to manually create a token with 'test-token' as the plain text
        // Since we can't control Sanctum's token generation, let's use a different approach
        \DB::table('personal_access_tokens')->updateOrInsert(
            ['tokenable_id' => $user->id, 'name' => 'test-app'],
            [
                'tokenable_type' => 'App\\Models\\User',
                'tokenable_id' => $user->id,
                'name' => 'test-app',
                'token' => hash('sha256', 'test-token'),
                'abilities' => '["*"]',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Create default categories
        $categories = [
            ['name' => 'Food & Dining', 'type' => 'expense'],
            ['name' => 'Transportation', 'type' => 'expense'],
            ['name' => 'Shopping', 'type' => 'expense'],
            ['name' => 'Entertainment', 'type' => 'expense'],
            ['name' => 'Bills & Utilities', 'type' => 'expense'],
            ['name' => 'Salary', 'type' => 'income'],
            ['name' => 'Freelance', 'type' => 'income'],
        ];

        foreach ($categories as $categoryData) {
            $category = \App\Models\Category::firstOrCreate([
                'name' => $categoryData['name'],
                'type' => $categoryData['type'],
            ]);

            // Link category to user
            \App\Models\UserCategory::firstOrCreate([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'is_active' => true,
            ]);
        }

        $this->info('Test user created successfully!');
        $this->info('Email: test@example.com');
        $this->info('Password: password');
        $this->info('Token: test-token');
        $this->info('User ID: ' . $user->id);
        $this->info('Categories: ' . count($categories) . ' categories linked');

        return 0;
    }
}
