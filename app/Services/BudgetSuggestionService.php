<?php

namespace App\Services;

use App\Models\User;
use App\Models\Category;
use Carbon\Carbon;

class BudgetSuggestionService
{
    public function getSuggestedBudgetAmounts(User $user, int $year, int $month)
    {
        $suggestions = [];
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();
        
        // Get system categories for loans and savings
        $loansCategory = Category::where('name', 'Loans')
            ->where('type', 'expense')
            ->first();
            
        $savingsCategory = Category::where('name', 'Savings or Investments')
            ->where('type', 'expense')
            ->first();

        // Calculate debt payment suggestions
        if ($loansCategory) {
            $debtSuggestion = $this->calculateDebtPaymentSuggestion($user, $year, $month);
            if ($debtSuggestion > 0) {
                $suggestions[$loansCategory->id] = [
                    'category' => $loansCategory,
                    'suggested_amount' => $debtSuggestion,
                    'reason' => 'Based on your debt payment schedule',
                    'breakdown' => $this->getDebtBreakdown($user, $year, $month)
                ];
            }
        }

        // Calculate savings/goal contribution suggestions
        if ($savingsCategory) {
            $goalSuggestion = $this->calculateGoalContributionSuggestion($user, $year, $month);
            if ($goalSuggestion > 0) {
                $suggestions[$savingsCategory->id] = [
                    'category' => $savingsCategory,
                    'suggested_amount' => $goalSuggestion,
                    'reason' => 'Based on your savings goals and target dates',
                    'breakdown' => $this->getGoalBreakdown($user, $year, $month)
                ];
            }
        }

        return $suggestions;
    }

    private function calculateDebtPaymentSuggestion(User $user, int $year, int $month): float
    {
        $totalDebtPayment = 0;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();

        foreach ($user->debts as $debt) {
            // Skip if debt is already paid off
            $paidAmount = $debt->transactions->sum('amount');
            $remainingBalance = $debt->initial_amount - $paidAmount;
            
            if ($remainingBalance <= 0) {
                continue;
            }

            // Get debt projections for this month
            $monthlyProjections = $debt->projections()
                ->whereBetween('projection_date', [$monthStart, $monthEnd])
                ->where('status', '!=', 'missed')
                ->get();

            if ($monthlyProjections->isNotEmpty()) {
                // Use actual projections if they exist
                $totalDebtPayment += $monthlyProjections->sum('amount');
            } else {
                // Calculate suggested payment based on target payoff date
                if ($debt->target_payoff_date) {
                    $targetDate = Carbon::parse($debt->target_payoff_date);
                    $monthsRemaining = max(1, Carbon::create($year, $month, 1)->diffInMonths($targetDate, false));
                    
                    if ($monthsRemaining > 0) {
                        $suggestedMonthlyPayment = $remainingBalance / $monthsRemaining;
                        $totalDebtPayment += $suggestedMonthlyPayment;
                    }
                } else {
                    // Use minimum payment (5% of remaining balance or $50, whichever is higher)
                    $minimumPayment = max(50, $remainingBalance * 0.05);
                    $totalDebtPayment += $minimumPayment;
                }
            }
        }

        return $totalDebtPayment;
    }

    private function calculateGoalContributionSuggestion(User $user, int $year, int $month): float
    {
        $totalGoalContribution = 0;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();

        foreach ($user->goals as $goal) {
            // Skip if goal is already completed
            $currentBalance = $goal->transactions->sum('amount');
            
            if ($currentBalance >= $goal->target_amount) {
                continue;
            }

            // Get goal projections for this month
            $monthlyProjections = $goal->projections()
                ->whereBetween('projection_date', [$monthStart, $monthEnd])
                ->where('status', '!=', 'missed')
                ->get();

            if ($monthlyProjections->isNotEmpty()) {
                // Use actual projections if they exist
                $totalGoalContribution += $monthlyProjections->sum('amount');
            } else {
                // Calculate suggested contribution based on target date
                $targetDate = Carbon::parse($goal->target_date);
                $monthsRemaining = max(1, Carbon::create($year, $month, 1)->diffInMonths($targetDate, false));
                
                if ($monthsRemaining > 0) {
                    $remainingAmount = $goal->target_amount - $currentBalance;
                    $suggestedMonthlyContribution = $remainingAmount / $monthsRemaining;
                    $totalGoalContribution += max(0, $suggestedMonthlyContribution);
                }
            }
        }

        return $totalGoalContribution;
    }

    private function getDebtBreakdown(User $user, int $year, int $month): array
    {
        $breakdown = [];
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();

        foreach ($user->debts as $debt) {
            $paidAmount = $debt->transactions->sum('amount');
            $remainingBalance = $debt->initial_amount - $paidAmount;
            
            if ($remainingBalance <= 0) {
                continue;
            }

            $monthlyProjections = $debt->projections()
                ->whereBetween('projection_date', [$monthStart, $monthEnd])
                ->where('status', '!=', 'missed')
                ->get();

            if ($monthlyProjections->isNotEmpty()) {
                $amount = $monthlyProjections->sum('amount');
                $source = 'planned projection';
            } else {
                if ($debt->target_payoff_date) {
                    $targetDate = Carbon::parse($debt->target_payoff_date);
                    $monthsRemaining = max(1, Carbon::create($year, $month, 1)->diffInMonths($targetDate, false));
                    $amount = $remainingBalance / $monthsRemaining;
                    $source = 'calculated to meet target date';
                } else {
                    $amount = max(50, $remainingBalance * 0.05);
                    $source = 'minimum payment (5%)';
                }
            }

            if ($amount > 0) {
                $breakdown[] = [
                    'name' => $debt->name,
                    'amount' => $amount,
                    'remaining_balance' => $remainingBalance,
                    'source' => $source
                ];
            }
        }

        return $breakdown;
    }

    private function getGoalBreakdown(User $user, int $year, int $month): array
    {
        $breakdown = [];
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();

        foreach ($user->goals as $goal) {
            $currentBalance = $goal->transactions->sum('amount');
            
            if ($currentBalance >= $goal->target_amount) {
                continue;
            }

            $monthlyProjections = $goal->projections()
                ->whereBetween('projection_date', [$monthStart, $monthEnd])
                ->where('status', '!=', 'missed')
                ->get();

            if ($monthlyProjections->isNotEmpty()) {
                $amount = $monthlyProjections->sum('amount');
                $source = 'planned projection';
            } else {
                $targetDate = Carbon::parse($goal->target_date);
                $monthsRemaining = max(1, Carbon::create($year, $month, 1)->diffInMonths($targetDate, false));
                $remainingAmount = $goal->target_amount - $currentBalance;
                $amount = $remainingAmount / $monthsRemaining;
                $source = 'calculated to meet target date';
            }

            if ($amount > 0) {
                $breakdown[] = [
                    'name' => $goal->name,
                    'amount' => $amount,
                    'remaining_amount' => $goal->target_amount - $currentBalance,
                    'progress' => ($currentBalance / $goal->target_amount) * 100,
                    'source' => $source
                ];
            }
        }

        return $breakdown;
    }

    public function getBudgetSuggestionsSummary(User $user, int $year, int $month): array
    {
        $suggestions = $this->getSuggestedBudgetAmounts($user, $year, $month);
        
        $summary = [
            'total_debt_payments' => 0,
            'total_goal_contributions' => 0,
            'total_suggested' => 0,
            'debt_count' => 0,
            'goal_count' => 0,
            'has_suggestions' => false
        ];

        foreach ($suggestions as $suggestion) {
            if ($suggestion['category']->name === 'Loans') {
                $summary['total_debt_payments'] = $suggestion['suggested_amount'];
                $summary['debt_count'] = count($suggestion['breakdown']);
            } elseif ($suggestion['category']->name === 'Savings or Investments') {
                $summary['total_goal_contributions'] = $suggestion['suggested_amount'];
                $summary['goal_count'] = count($suggestion['breakdown']);
            }
        }

        $summary['total_suggested'] = $summary['total_debt_payments'] + $summary['total_goal_contributions'];
        $summary['has_suggestions'] = $summary['total_suggested'] > 0;

        return $summary;
    }
}