<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GoalProjection;
use App\Services\GoalProjectionService;
use App\Services\DebtProjectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, GoalProjectionService $goalService, DebtProjectionService $debtService)
    {
        $user = $request->user();

        if (!$user->hasCompletedOnboarding()) {
            return response()->json([
                'onboarding_completed' => false,
                'message' => 'User needs to complete onboarding'
            ]);
        }

        $now = Carbon::now();
        $startDate = $now->copy()->startOfMonth();
        $endDate = $now->copy()->endOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // Budget overview calculation
        $budgets = $user->budgets()
            ->where('month', $now->month)->where('year', $now->year)
            ->with('category')
            ->get();

        $totalBudgetedIncome = $budgets->where('category.type', 'income')->sum('amount');
        $totalActualIncome = $user->transactions()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereHas('category', fn($q) => $q->where('type', 'income'))
            ->sum('amount');

        $totalBudgetedExpense = $budgets->where('category.type', 'expense')->sum('amount');
        $totalActualExpense = $user->transactions()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->sum('amount');

        $netActual = $totalActualIncome - $totalActualExpense;

        $budgetSummary = [
            'income' => ['budgeted' => $totalBudgetedIncome, 'actual' => $totalActualIncome],
            'expenses' => ['budgeted' => $totalBudgetedExpense, 'actual' => $totalActualExpense],
            'net' => $netActual,
        ];

        // Expense chart data
        $parentCategories = Category::whereIn('id', function($query) use ($user) {
                $query->select('category_id')
                      ->from('user_categories')
                      ->where('user_id', $user->id)
                      ->where('is_active', true);
            })
            ->where('type', 'expense')
            ->whereNull('parent_id')
            ->with(['children' => function ($query) use ($user) {
                $query->whereIn('id', function($subQuery) use ($user) {
                    $subQuery->select('category_id')
                             ->from('user_categories')
                             ->where('user_id', $user->id)
                             ->where('is_active', true);
                });
            }])
            ->get();

        $expenseChartSummary = [];
        foreach ($parentCategories as $parent) {
            $childIds = $parent->children->pluck('id');
            $actualSpending = $user->transactions()
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->whereIn('category_id', $childIds)
                ->sum('amount');
            if ($actualSpending > 0) {
                $expenseChartSummary[] = ['name' => $parent->name, 'actual' => $actualSpending];
            }
        }

        $expenseChartData = [
            'labels' => collect($expenseChartSummary)->pluck('name'),
            'data' => collect($expenseChartSummary)->pluck('actual'),
        ];

        // Financial insights
        $insights = $this->calculateFinancialInsights($user, $goalService, $debtService, $now, $startDate, $endDate, $lastMonthStart, $lastMonthEnd, $totalActualIncome, $budgets);

        // Proactive alerts
        $alerts = $this->calculateProactiveAlerts($user, $now, $budgets, $totalActualIncome);

        // Pending reconciliations
        $pendingReconciliations = GoalProjection::whereHas('goal', fn($q) => $q->where('user_id', $user->id))
            ->where('projection_date', '<', $now)
            ->where('status', 'planned')
            ->orderBy('projection_date')
            ->limit(3)
            ->get();

        return response()->json([
            'onboarding_completed' => true,
            'budget_summary' => $budgetSummary,
            'expense_chart_data' => $expenseChartData,
            'pending_reconciliations' => $pendingReconciliations,
            'goals' => $user->goals()->latest()->get(),
            'insights' => $insights,
            'alerts' => $alerts,
        ]);
    }

    private function calculateFinancialInsights($user, $goalService, $debtService, $now, $startDate, $endDate, $lastMonthStart, $lastMonthEnd, $totalActualIncome, $budgets)
    {
        // Debt payoff velocity
        $debts = $user->debts;
        $totalCurrentDebt = 0;
        $totalLastMonthDebt = 0;
        $debtPayoffVelocity = 0;

        foreach ($debts as $debt) {
            $result = $debtService->calculate($debt);
            $totalCurrentDebt += $result['current_balance'];

            $lastMonthTransactions = $debt->transactions()
                ->whereBetween('transaction_date', [$lastMonthStart, $lastMonthEnd])
                ->sum('amount');
            $totalLastMonthDebt += ($result['current_balance'] + $lastMonthTransactions);
        }

        if ($totalLastMonthDebt > 0) {
            $debtPayoffVelocity = (($totalLastMonthDebt - $totalCurrentDebt) / $totalLastMonthDebt) * 100;
        }

        // Savings rate
        $goalContributions = 0;
        foreach ($user->goals as $goal) {
            $goalContributions += $goal->transactions()
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');
        }

        $savingsRate = $totalActualIncome > 0 ? ($goalContributions / $totalActualIncome) * 100 : 0;

        // Budget variance alerts
        $budgetAlerts = [];
        foreach ($budgets->where('category.type', 'expense') as $budget) {
            $actualSpent = $user->transactions()
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->where('category_id', $budget->category_id)
                ->sum('amount');

            $percentageUsed = $budget->amount > 0 ? ($actualSpent / $budget->amount) * 100 : 0;

            if ($percentageUsed >= 80) {
                $budgetAlerts[] = [
                    'category' => $budget->category->name,
                    'percentage' => round($percentageUsed),
                    'spent' => $actualSpent,
                    'budget' => $budget->amount,
                    'severity' => $percentageUsed >= 100 ? 'danger' : ($percentageUsed >= 90 ? 'warning' : 'info')
                ];
            }
        }

        return [
            'debt_payoff_velocity' => round($debtPayoffVelocity, 1),
            'savings_rate' => round($savingsRate, 1),
            'budget_alerts' => $budgetAlerts,
            'total_current_debt' => $totalCurrentDebt,
            'goal_contributions_this_month' => $goalContributions,
        ];
    }

    private function calculateProactiveAlerts($user, $now, $budgets, $totalActualIncome)
    {
        $alerts = [];

        // Goal deadline reminders
        $goals = $user->goals()->where('target_date', '>', $now)->get();
        foreach ($goals as $goal) {
            $daysUntilTarget = $now->diffInDays(Carbon::parse($goal->target_date));

            if (in_array($daysUntilTarget, [30, 60, 90])) {
                $alerts[] = [
                    'type' => 'goal_deadline',
                    'severity' => $daysUntilTarget <= 30 ? 'warning' : 'info',
                    'title' => "Goal Deadline Approaching",
                    'message' => "Your goal '{$goal->name}' is due in {$daysUntilTarget} days.",
                    'action_url' => "/goals/{$goal->id}",
                    'action_text' => 'View Goal'
                ];
            }
        }

        // Debt payment reminders
        $debts = $user->debts;
        foreach ($debts as $debt) {
            $missedProjections = $debt->projections()
                ->where('projection_date', '<', $now)
                ->where('status', 'planned')
                ->count();

            if ($missedProjections > 0) {
                $alerts[] = [
                    'type' => 'debt_behind',
                    'severity' => 'warning',
                    'title' => "Debt Payment Behind Schedule",
                    'message' => "You have {$missedProjections} missed payment(s) for '{$debt->name}'.",
                    'action_url' => "/debts/{$debt->id}",
                    'action_text' => 'Update Payments'
                ];
            }
        }

        // Budget warnings
        foreach ($budgets->where('category.type', 'expense') as $budget) {
            $actualSpent = $user->transactions()
                ->whereMonth('transaction_date', $now->month)
                ->whereYear('transaction_date', $now->year)
                ->where('category_id', $budget->category_id)
                ->sum('amount');

            $percentageUsed = $budget->amount > 0 ? ($actualSpent / $budget->amount) * 100 : 0;

            if ($percentageUsed >= 80 && $percentageUsed < 100) {
                $alerts[] = [
                    'type' => 'budget_warning',
                    'severity' => $percentageUsed >= 90 ? 'warning' : 'info',
                    'title' => "Budget Alert",
                    'message' => "You've spent " . round($percentageUsed) . "% of your {$budget->category->name} budget.",
                    'action_url' => '/budget',
                    'action_text' => 'View Budget'
                ];
            }
        }

        return $alerts;
    }
}