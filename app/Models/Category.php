<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Category extends Model
{
    protected $fillable = ['name', 'type', 'parent_id'];


    /**
     * Get the transactions for the category.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Scope a query to only include categories selected by the current user.
     * If user hasn't selected any categories yet, show all categories.
     */
    public function scopeForCurrentUser(Builder $query): void
    {
        $userId = Auth::id();
        
        // Check if user has selected any categories
        $hasSelectedCategories = UserCategory::where('user_id', $userId)
            ->where('is_active', true)
            ->exists();
        
        if ($hasSelectedCategories) {
            // Show only user-selected categories
            $query->whereIn('id', function($subQuery) use ($userId) {
                $subQuery->select('category_id')
                    ->from('user_categories')
                    ->where('user_id', $userId)
                    ->where('is_active', true);
            });
        }
        // If no categories selected, show all categories (no filter needed)
    }
}