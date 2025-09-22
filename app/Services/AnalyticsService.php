<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function getSpendingPatterns(User $user, string $period = '6months')
    {
        $startDate = match($period) {
            '3months' => Carbon::now()->subMonths(3),
            '6months' => Carbon::now()->subMonths(6),
            '1year' => Carbon::now()->subYear(),
            '2years' => Carbon::now()->subYears(2),
            default => Carbon::now()->subMonths(6)
        };

        // Monthly spending by category
        $monthlySpending = $user->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.type', 'expense')
            ->where('transaction_date', '>=', $startDate)
            ->select(
                DB::raw('YEAR(transaction_date) as year'),
                DB::raw('MONTH(transaction_date) as month'),
                'categories.name as category',
                'categories.id as category_id',
                DB::raw('SUM(ABS(amount)) as total')
            )
            ->groupBy('year', 'month', 'categories.id', 'categories.name')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Top spending categories
        $topCategories = $user->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.type', 'expense')
            ->where('transaction_date', '>=', $startDate)
            ->select('categories.name as category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Weekly spending trends
        $weeklyTrends = $user->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.type', 'expense')
            ->where('transaction_date', '>=', Carbon::now()->subWeeks(12))
            ->select(
                DB::raw('YEAR(transaction_date) as year'),
                DB::raw('WEEK(transaction_date) as week'),
                DB::raw('SUM(ABS(amount)) as total')
            )
            ->groupBy('year', 'week')
            ->orderBy('year')
            ->orderBy('week')
            ->get();

        return [
            'monthly_spending' => $monthlySpending,
            'top_categories' => $topCategories,
            'weekly_trends' => $weeklyTrends,
            'period' => $period,
            'start_date' => $startDate->format('Y-m-d')
        ];
    }

    public function getFinancialHealthScore(User $user)
    {
        $score = 0;
        $factors = [];

        // Get last 3 months of data
        $startDate = Carbon::now()->subMonths(3);
        
        // Factor 1: Savings Rate (30 points max)
        $income = $user->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.type', 'income')
            ->where('transaction_date', '>=', $startDate)
            ->sum('amount');

        $expenses = $user->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.type', 'expense')
            ->where('transaction_date', '>=', $startDate)
            ->sum('amount');

        $savingsRate = $income > 0 ? (($income - abs($expenses)) / $income) * 100 : 0;
        $savingsScore = min(30, ($savingsRate / 20) * 30); // 20% savings = full 30 points
        $score += $savingsScore;
        $factors['savings_rate'] = [
            'value' => round($savingsRate, 1),
            'score' => round($savingsScore, 1),
            'max_score' => 30,
            'description' => 'Percentage of income saved'
        ];

        // Factor 2: Budget Adherence (25 points max)
        $budgetAdherence = $this->calculateBudgetAdherence($user);
        $budgetScore = ($budgetAdherence / 100) * 25;
        $score += $budgetScore;
        $factors['budget_adherence'] = [
            'value' => round($budgetAdherence, 1),
            'score' => round($budgetScore, 1),
            'max_score' => 25,
            'description' => 'How well you stick to your budgets'
        ];

        // Factor 3: Goal Progress (20 points max)
        $goalProgress = $this->calculateGoalProgress($user);
        $goalScore = ($goalProgress / 100) * 20;
        $score += $goalScore;
        $factors['goal_progress'] = [
            'value' => round($goalProgress, 1),
            'score' => round($goalScore, 1),
            'max_score' => 20,
            'description' => 'Average progress toward your goals'
        ];

        // Factor 4: Debt Management (15 points max)
        $debtProgress = $this->calculateDebtProgress($user);
        $debtScore = ($debtProgress / 100) * 15;
        $score += $debtScore;
        $factors['debt_progress'] = [
            'value' => round($debtProgress, 1),
            'score' => round($debtScore, 1),
            'max_score' => 15,
            'description' => 'Progress in reducing debts'
        ];

        // Factor 5: Financial Consistency (10 points max)
        $consistency = $this->calculateFinancialConsistency($user);
        $consistencyScore = ($consistency / 100) * 10;
        $score += $consistencyScore;
        $factors['consistency'] = [
            'value' => round($consistency, 1),
            'score' => round($consistencyScore, 1),
            'max_score' => 10,
            'description' => 'Regularity of financial tracking'
        ];

        return [
            'total_score' => round($score, 1),
            'max_score' => 100,
            'grade' => $this->getHealthGrade($score),
            'factors' => $factors,
            'recommendations' => $this->getHealthRecommendations($factors)
        ];
    }

    private function calculateBudgetAdherence(User $user)
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        
        $budgets = $user->budgets()
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->with('category')
            ->get();

        if ($budgets->isEmpty()) {
            return 0;
        }

        $adherenceScores = [];
        foreach ($budgets as $budget) {
            $actualSpending = $user->transactions()
                ->where('category_id', $budget->category_id)
                ->whereMonth('transaction_date', $currentMonth)
                ->whereYear('transaction_date', $currentYear)
                ->sum('amount');

            if ($budget->amount > 0) {
                $overage = abs($actualSpending) - $budget->amount;
                $adherencePercentage = $overage > 0 ? max(0, 100 - (($overage / $budget->amount) * 100)) : 100;
            } else {
                $adherencePercentage = 100;
            }
            $adherenceScores[] = max(0, $adherencePercentage);
        }

        return collect($adherenceScores)->average();
    }

    private function calculateGoalProgress(User $user)
    {
        $goals = $user->goals;
        if ($goals->isEmpty()) {
            return 50; // Neutral score if no goals
        }

        $progressScores = [];
        foreach ($goals as $goal) {
            $currentBalance = $goal->transactions->sum('amount');
            $progress = $goal->target_amount > 0 ? ($currentBalance / $goal->target_amount) * 100 : 0;
            $progressScores[] = min(100, $progress);
        }

        return collect($progressScores)->average();
    }

    private function calculateDebtProgress(User $user)
    {
        $debts = $user->debts;
        if ($debts->isEmpty()) {
            return 100; // Perfect score if no debts
        }

        $progressScores = [];
        foreach ($debts as $debt) {
            $paidAmount = $debt->transactions->sum('amount');
            $progress = $debt->initial_amount > 0 ? ($paidAmount / $debt->initial_amount) * 100 : 0;
            $progressScores[] = min(100, $progress);
        }

        return collect($progressScores)->average();
    }

    private function calculateFinancialConsistency(User $user)
    {
        // Check transaction frequency over last 3 months
        $weeks = [];
        for ($i = 0; $i < 12; $i++) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd = Carbon::now()->subWeeks($i)->endOfWeek();
            
            $transactionCount = $user->transactions()
                ->whereBetween('transaction_date', [$weekStart, $weekEnd])
                ->count();
                
            $weeks[] = $transactionCount > 0 ? 1 : 0;
        }

        return (array_sum($weeks) / count($weeks)) * 100;
    }

    private function getHealthGrade($score)
    {
        return match(true) {
            $score >= 90 => 'A+',
            $score >= 85 => 'A',
            $score >= 80 => 'A-',
            $score >= 75 => 'B+',
            $score >= 70 => 'B',
            $score >= 65 => 'B-',
            $score >= 60 => 'C+',
            $score >= 55 => 'C',
            $score >= 50 => 'C-',
            $score >= 45 => 'D+',
            $score >= 40 => 'D',
            default => 'F'
        };
    }

    private function getHealthRecommendations(array $factors)
    {
        $recommendations = [];

        if ($factors['savings_rate']['value'] < 10) {
            $recommendations[] = "💰 Increase your savings rate. Aim for at least 10% of your income.";
        }

        if ($factors['budget_adherence']['value'] < 70) {
            $recommendations[] = "📊 Focus on sticking to your budgets. Consider adjusting unrealistic budget amounts.";
        }

        if ($factors['goal_progress']['value'] < 50) {
            $recommendations[] = "🎯 Review your goals. Consider breaking large goals into smaller, achievable milestones.";
        }

        if ($factors['consistency']['value'] < 80) {
            $recommendations[] = "📱 Track transactions more regularly. Consistency leads to better financial awareness.";
        }

        if (empty($recommendations)) {
            $recommendations[] = "🌟 Great job! Your financial health looks excellent. Keep up the good work!";
        }

        return $recommendations;
    }

    public function getPredictiveInsights(User $user)
    {
        $insights = [];

        // Goal completion predictions
        foreach ($user->goals as $goal) {
            $currentBalance = $goal->transactions->sum('amount');
            $monthlyProgress = $this->calculateMonthlyGoalProgress($goal);
            
            if ($monthlyProgress > 0) {
                $remainingAmount = $goal->target_amount - $currentBalance;
                $monthsToCompletion = $monthlyProgress > 0 ? $remainingAmount / $monthlyProgress : 0;
                $predictedDate = Carbon::now()->addMonths(ceil($monthsToCompletion));
                
                $insights[] = [
                    'type' => 'goal_prediction',
                    'title' => "Goal: {$goal->name}",
                    'message' => "At your current pace of " . $user->formatAmount($monthlyProgress) . "/month, you'll reach this goal by " . $predictedDate->format('M Y'),
                    'data' => [
                        'goal_id' => $goal->id,
                        'current_pace' => $monthlyProgress,
                        'predicted_date' => $predictedDate,
                        'months_remaining' => ceil($monthsToCompletion)
                    ]
                ];
            }
        }

        // Spending trend predictions
        $spendingTrend = $this->calculateSpendingTrend($user);
        if ($spendingTrend['trend'] !== 'stable') {
            $insights[] = [
                'type' => 'spending_trend',
                'title' => 'Spending Pattern Alert',
                'message' => $spendingTrend['message'],
                'data' => $spendingTrend
            ];
        }

        return $insights;
    }

    private function calculateMonthlyGoalProgress($goal)
    {
        // Get transactions from complete months only (not current incomplete month)
        $completeMonthsData = [];
        
        // Check last 6 complete months for better accuracy
        for ($i = 1; $i <= 6; $i++) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            
            $monthTotal = $goal->transactions()
                ->whereBetween('transaction_date', [$monthStart, $monthEnd])
                ->sum('amount');
                
            if ($monthTotal > 0) {
                $completeMonthsData[] = $monthTotal;
            }
        }

        if (empty($completeMonthsData)) {
            return 0;
        }

        // Return average of complete months with contributions
        return collect($completeMonthsData)->average();
    }

    private function calculateSpendingTrend(User $user)
    {
        $last3Months = [];
        for ($i = 0; $i < 3; $i++) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            
            $spending = $user->transactions()
                ->join('categories', 'transactions.category_id', '=', 'categories.id')
                ->where('categories.type', 'expense')
                ->whereBetween('transaction_date', [$monthStart, $monthEnd])
                ->sum('amount');
                
            $last3Months[] = abs($spending);
        }

        $last3Months = array_reverse($last3Months); // Oldest to newest
        
        if (count($last3Months) >= 2) {
            $change = $last3Months[0] > 0 ? ($last3Months[2] - $last3Months[0]) / $last3Months[0] * 100 : 0;
            
            if ($change > 15) {
                return [
                    'trend' => 'increasing',
                    'change_percent' => round($change, 1),
                    'message' => "Your spending has increased by " . round($change, 1) . "% over the last 3 months. Consider reviewing your budget."
                ];
            } elseif ($change < -15) {
                return [
                    'trend' => 'decreasing',
                    'change_percent' => round($change, 1),
                    'message' => "Great job! Your spending has decreased by " . abs(round($change, 1)) . "% over the last 3 months."
                ];
            }
        }

        return ['trend' => 'stable', 'message' => 'Your spending patterns are stable.'];
    }
}