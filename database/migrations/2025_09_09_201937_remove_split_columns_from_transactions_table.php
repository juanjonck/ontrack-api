<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite approach: recreate table without foreign key columns
            $this->recreateTableForSqlite();
        } else {
            // MySQL/PostgreSQL approach: drop foreign key first, then columns
            Schema::table('transactions', function (Blueprint $table) {
                // Drop foreign key constraint first
                $table->dropForeign(['parent_transaction_id']);
                // Then drop the columns
                $table->dropColumn(['parent_transaction_id', 'is_split', 'split_total']);
            });
        }
    }

    private function recreateTableForSqlite(): void
    {
        // Step 1: Get all existing data
        $transactions = DB::table('transactions')->get()->toArray();

        // Step 2: Create backup table
        Schema::rename('transactions', 'transactions_backup');

        // Step 3: Recreate transactions table without the dropped columns
        // You'll need to adjust this based on your actual table structure
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            
            // Add all your existing columns EXCEPT the ones being dropped
            // Replace these with your actual column definitions:
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->date('transaction_date');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->string('type'); // income, expense, transfer, etc.
            
            // Add any other columns you have, but NOT:
            // - parent_transaction_id
            // - is_split  
            // - split_total
            
            $table->timestamps();
            
            // Add back any other foreign keys (but not parent_transaction_id)
            // $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            // $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        // Step 4: Copy data back (excluding the dropped columns)
        foreach ($transactions as $transaction) {
            $transactionArray = (array) $transaction;
            
            // Remove the columns we're dropping
            unset($transactionArray['parent_transaction_id']);
            unset($transactionArray['is_split']);
            unset($transactionArray['split_total']);
            
            DB::table('transactions')->insert($transactionArray);
        }

        // Step 5: Drop the backup table
        Schema::dropIfExists('transactions_backup');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Recreate split-related columns if needed to rollback
            $table->unsignedBigInteger('parent_transaction_id')->nullable();
            $table->boolean('is_split')->default(false);
            $table->decimal('split_total', 15, 2)->nullable();
            
            $table->foreign('parent_transaction_id')->references('id')->on('transactions')->onDelete('cascade');
        });
    }
};