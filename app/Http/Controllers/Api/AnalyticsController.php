<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

        // Monthly spending by category
        $monthlySpending = Transaction::where('user_id', $user->id)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->with('category')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get();

        // Income vs Expenses over time
        $incomeVsExpenses = Transaction::where('user_id', $user->id)
            ->whereYear('transaction_date', $year)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw('MONTH(transaction_date) as month, categories.type, SUM(amount) as total')
            ->groupBy('month', 'categories.type')
            ->orderBy('month')
            ->get();

        // Budget vs Actual spending
        $budgetComparison = Budget::where('user_id', $user->id)
            ->whereYear('month', $year)
            ->whereMonth('month', $month)
            ->with('category')
            ->get()
            ->map(function ($budget) use ($year, $month) {
                $actualSpent = Transaction::where('user_id', $budget->user_id)
                    ->where('category_id', $budget->category_id)
                    ->whereYear('transaction_date', $year)
                    ->whereMonth('transaction_date', $month)
                    ->sum('amount');

                return [
                    'category' => $budget->category->name,
                    'budgeted' => $budget->amount,
                    'actual' => $actualSpent,
                    'variance' => $budget->amount - $actualSpent,
                ];
            });

        // Top spending categories
        $topCategories = Transaction::where('user_id', $user->id)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.type', 'expense')
            ->selectRaw('categories.name, SUM(transactions.amount) as total')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Recent transactions trend
        $recentTrend = Transaction::where('user_id', $user->id)
            ->whereDate('transaction_date', '>=', Carbon::now()->subDays(30))
            ->selectRaw('DATE(transaction_date) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'analyticsData' => [
                'monthlySpending' => $monthlySpending,
                'incomeVsExpenses' => $incomeVsExpenses,
                'budgetComparison' => $budgetComparison,
                'topCategories' => $topCategories,
                'recentTrend' => $recentTrend,
                'currentPeriod' => [
                    'year' => $year,
                    'month' => $month,
                ],
            ],
        ]);
    }
}