<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use App\Services\CurrencyService;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'currency',
        'onboarding_completed',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'onboarding_completed' => 'boolean',
        ];
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }


    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    /**
     * Get the budgets for the user.
    */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Get the user's selected categories.
     */
    public function userCategories(): HasMany
    {
        return $this->hasMany(UserCategory::class);
    }

    /**
     * Get the user's active selected categories.
     */
    public function selectedCategories()
    {
        return $this->belongsToMany(Category::class, 'user_categories')
                    ->wherePivot('is_active', true)
                    ->withTimestamps();
    }

    /**
     * Get the user's total goal amount.
     */
    public function totalGoalAmount(): float
    {
        return $this->goals()->sum('target_amount');
    }

    /**
     * Get the user's active goals.
     */
    public function activeGoals()
    {
        return $this->goals()->where('target_date', '>=', now());
    }

    /**
     * Get the user's currency symbol.
     */
    public function getCurrencySymbol(): string
    {
        return CurrencyService::getCurrencySymbol($this->currency ?? 'USD');
    }

    /**
     * Get the user's currency flag.
     */
    public function getCurrencyFlag(): string
    {
        return CurrencyService::getCurrencyFlag($this->currency ?? 'USD');
    }

    /**
     * Format an amount with the user's currency.
     */
    public function formatAmount(float $amount): string
    {
        return CurrencyService::formatAmount($amount, $this->currency ?? 'USD');
    }

    /**
     * Check if the user has completed onboarding.
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed;
    }
}