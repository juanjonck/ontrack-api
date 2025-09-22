<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add parent transaction ID for split transactions
            $table->foreignId('parent_transaction_id')->nullable()->constrained('transactions')->cascadeOnDelete();
            
            // Add is_split flag to identify if this transaction has been split
            $table->boolean('is_split')->default(false);
            
            // Add split_total to track the original amount for parent transactions
            $table->decimal('split_total', 15, 2)->nullable();
            
            // Add index for performance
            $table->index(['parent_transaction_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['parent_transaction_id', 'user_id']);
            $table->dropForeign(['parent_transaction_id']);
            $table->dropColumn(['parent_transaction_id', 'is_split', 'split_total']);
        });
    }
};