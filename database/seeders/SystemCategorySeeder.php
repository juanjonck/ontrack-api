<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class SystemCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Expenses
            ['name' => 'Housing', 'type' => 'expense', 'children' => [
                ['name' => 'Home Loan/Rent', 'type' => 'expense'],
                ['name' => 'Phone/Cell', 'type' => 'expense'],
                ['name' => 'Electricity', 'type' => 'expense'],
                ['name' => 'Water & Rates', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Transportation', 'type' => 'expense', 'children' => [
                ['name' => 'Car Loan Repayment', 'type' => 'expense'],
                ['name' => 'Bus/Train/Taxi Fare', 'type' => 'expense'],
                ['name' => 'Insurance', 'type' => 'expense'],
                ['name' => 'Licensing', 'type' => 'expense'],
                ['name' => 'Fuel', 'type' => 'expense'],
                ['name' => 'Maintenance', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Insurance', 'type' => 'expense', 'children' => [
                ['name' => 'Home', 'type' => 'expense'],
                ['name' => 'Health', 'type' => 'expense'],
                ['name' => 'Life', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Food', 'type' => 'expense', 'children' => [
                ['name' => 'Groceries', 'type' => 'expense'],
                ['name' => 'Dining out', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Pets', 'type' => 'expense', 'children' => [
                ['name' => 'Food', 'type' => 'expense'],
                ['name' => 'Medical', 'type' => 'expense'],
                ['name' => 'Grooming', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Personal Care', 'type' => 'expense', 'children' => [
                ['name' => 'Medical', 'type' => 'expense'],
                ['name' => 'Hair/Nails', 'type' => 'expense'],
                ['name' => 'Clothing', 'type' => 'expense'],
                ['name' => 'Health Club', 'type' => 'expense'],
                ['name' => 'Club Fees', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Entertainment', 'type' => 'expense', 'children' => [
                ['name' => 'Subscriptions (Netflix, Spotify, etc.)', 'type' => 'expense'],
                ['name' => 'Movies', 'type' => 'expense'],
                ['name' => 'Concerts', 'type' => 'expense'],
                ['name' => 'Sporting Events', 'type' => 'expense'],
                ['name' => 'Internet', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Loans', 'type' => 'expense', 'children' => [
                ['name' => 'Personal', 'type' => 'expense'],
                ['name' => 'Student', 'type' => 'expense'],
                ['name' => 'Credit Card 1', 'type' => 'expense'],
                ['name' => 'Credit Card 2', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Children', 'type' => 'expense', 'children' => [
                ['name' => 'School Fees', 'type' => 'expense'],
                ['name' => 'Extramural Fees', 'type' => 'expense'],
                ['name' => 'School Books', 'type' => 'expense'],
                ['name' => 'School Clothes', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Savings or Investments', 'type' => 'expense', 'children' => [
                ['name' => 'Retirement Account', 'type' => 'expense'],
                ['name' => 'Investment Account', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Gifts and Donations', 'type' => 'expense', 'children' => [
                ['name' => 'Charity 1', 'type' => 'expense'],
                ['name' => 'Charity 2', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
            ['name' => 'Legal', 'type' => 'expense', 'children' => [
                ['name' => 'Attorney', 'type' => 'expense'],
                ['name' => 'Maintenance', 'type' => 'expense'],
                ['name' => 'Other', 'type' => 'expense'],
            ]],
             // Incomes
            ['name' => 'Primary Job', 'type' => 'income', 'children' => [
                 ['name' => 'Salary', 'type' => 'income'],
                 ['name' => 'Bonus', 'type' => 'income'],
                 ['name' => 'Side Hustle 1', 'type' => 'income'],
                 ['name' => 'Side Hustle 2', 'type' => 'income'],
            ]],
        ];

        foreach ($categories as $parentData) {
            $children = $parentData['children'] ?? [];
            unset($parentData['children']);

            $parent = Category::create($parentData);

            foreach ($children as $childData) {
                $parent->children()->create($childData);
            }
        }
    }
}