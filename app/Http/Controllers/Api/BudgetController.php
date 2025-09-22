<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $year = $request->input('year', Carbon::now()->year);
            $month = $request->input('month', Carbon::now()->month);
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // Check if user has selected categories
            $hasSelectedCategories = $user->userCategories()->where('is_active', true)->exists();
            if (!$hasSelectedCategories) {
                return response()->json([
                    'status' => false,
                    'message' => 'No categories selected',
                    'needs_category_selection' => true
                ], 422);
            }

        // Get categories with relationships
        $categories = Category::whereIn('id', function($query) use ($user) {
                $query->select('category_id')
                      ->from('user_categories')
                      ->where('user_id', $user->id)
                      ->where('is_active', true);
            })
            ->whereNull('parent_id')
            ->with(['children' => function ($query) use ($user) {
                $query->whereIn('id', function($subQuery) use ($user) {
                    $subQuery->select('category_id')
                             ->from('user_categories')
                             ->where('user_id', $user->id)
                             ->where('is_active', true);
                })->orderBy('name');
            }])
            ->orderBy('type')->orderBy('name')
            ->get();

        // Get budget entries
        $budgets = $user->budgets()
            ->where('month', $month)
            ->where('year', $year)
            ->pluck('amount', 'category_id');

        // Get actual spending
        $actuals = $user->transactions()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        // Get budget suggestions
        $budgetSuggestionService = app(BudgetSuggestionService::class);
        $budgetSuggestions = $budgetSuggestionService->getSuggestedBudgetAmounts($user, $year, $month);
        $suggestionsSummary = $budgetSuggestionService->getBudgetSuggestionsSummary($user, $year, $month);

            return response()->json([
                'status' => true,
                'data' => [
                    'budgets' => $budgets,
                    'categories' => $categories,
                    'actuals' => $actuals,
                    'summary' => [
                        'total_budgeted' => $budgets->sum(),
                        'total_spent' => $actuals->sum(),
                        'remaining' => $budgets->sum() - $actuals->sum(),
                    ],
                    'current_year' => $year,
                    'current_month' => $month,
                    'budget_suggestions' => $budgetSuggestions,
                    'suggestions_summary' => $suggestionsSummary,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch budgets',
                'data' => [
                    'budgets' => [],
                    'categories' => [],
                    'actuals' => [],
                ]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'budgets' => 'required|array',
            'budgets.*' => 'nullable|numeric|min:0',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000',
        ]);

        $user = $request->user();

        foreach ($validated['budgets'] as $categoryId => $amount) {
            if (is_null($amount) || $amount == 0) {
                Budget::where('user_id', $user->id)
                    ->where('category_id', $categoryId)
                    ->where('month', $validated['month'])
                    ->where('year', $validated['year'])
                    ->delete();
            } else {
                Budget::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'category_id' => $categoryId,
                        'month' => $validated['month'],
                        'year' => $validated['year'],
                    ],
                    [
                        'amount' => $amount,
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Budget updated successfully'
        ]);
    }

    public function getTemplates(Request $request)
    {
        $user = $request->user();

        $templates = $user->budgets()
            ->select('month', 'year', DB::raw('COUNT(*) as category_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('month', 'year')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($template) {
                return [
                    'month' => $template->month,
                    'year' => $template->year,
                    'display_name' => Carbon::create($template->year, $template->month)->format('F Y'),
                    'category_count' => $template->category_count,
                    'total_amount' => $template->total_amount,
                ];
            });

        return response()->json($templates);
    }

    public function copyTemplate(Request $request)
    {
        $validated = $request->validate([
            'source_month' => 'required|integer|between:1,12',
            'source_year' => 'required|integer|min:2000',
            'target_month' => 'required|integer|between:1,12',
            'target_year' => 'required|integer|min:2000',
        ]);

        $user = $request->user();

        $sourceBudgets = $user->budgets()
            ->where('month', $validated['source_month'])
            ->where('year', $validated['source_year'])
            ->get();

        if ($sourceBudgets->isEmpty()) {
            return response()->json([
                'message' => 'No budget template found for the selected period.'
            ], 404);
        }

        $copiedCount = 0;
        foreach ($sourceBudgets as $sourceBudget) {
            Budget::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'category_id' => $sourceBudget->category_id,
                    'month' => $validated['target_month'],
                    'year' => $validated['target_year'],
                ],
                [
                    'amount' => $sourceBudget->amount,
                ]
            );
            $copiedCount++;
        }

        return response()->json([
            'message' => 'Successfully copied budget template from '.
                        Carbon::create($validated['source_year'], $validated['source_month'])->format('F Y').
                        ' to '.
                        Carbon::create($validated['target_year'], $validated['target_month'])->format('F Y'),
            'copied_categories' => $copiedCount,
        ]);
    }
}