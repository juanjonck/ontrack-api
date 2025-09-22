<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\GoalProjection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GoalController extends Controller
{
    /**
     * Display a listing of the user's goals.
     */
    public function index(Request $request)
    {
        try {
            $goals = $request->user()->goals()->with(['projections'])->latest()->get();

            // Calculate current balance for each goal
            $goals->map(function ($goal) {
                $currentBalance = $goal->transactions()->sum('amount');
                $goal->current_balance = $currentBalance;
                $goal->progress_percentage = $goal->target_amount > 0
                    ? ($currentBalance / $goal->target_amount) * 100
                    : 0;
                return $goal;
            });

            return response()->json([
                'status' => true,
                'data' => [
                    'goals' => $goals,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch goals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created goal.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'target_amount' => 'required|numeric|min:0',
                'target_date' => 'required|date|after:today',
                'yearly_interest_rate' => 'nullable|numeric|min:0|max:100',
                'description' => 'nullable|string|max:1000',
                'projections' => 'required|array|min:1',
                'projections.*.description' => 'required|string|max:255',
                'projections.*.amount' => 'required|numeric',
                'projections.*.type' => 'required|in:income,expense',
                'projections.*.frequency' => 'required|in:once,monthly',
                'projections.*.date' => 'required_if:projections.*.frequency,once|nullable|date',
                'projections.*.start_date' => 'required_if:projections.*.frequency,monthly|nullable|date',
                'projections.*.end_date' => 'required_if:projections.*.frequency,monthly|nullable|date|after_or_equal:projections.*.start_date',
            ]);

            $goal = $request->user()->goals()->create([
                'name' => $validated['name'],
                'target_amount' => $validated['target_amount'],
                'target_date' => $validated['target_date'],
                'yearly_interest_rate' => $validated['yearly_interest_rate'] ?? 0,
                'description' => $validated['description'] ?? null,
            ]);

            $this->createProjections($goal, $validated['projections']);

            return response()->json([
                'status' => true,
                'message' => 'Goal created successfully',
                'data' => ['goal' => $goal->load('projections')]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified goal.
     */
    public function show(Request $request, $id)
    {
        try {
            $goal = $request->user()->goals()->with(['projections', 'transactions'])->findOrFail($id);

            $currentBalance = $goal->transactions()->sum('amount');
            $goal->current_balance = $currentBalance;
            $goal->progress_percentage = $goal->target_amount > 0
                ? ($currentBalance / $goal->target_amount) * 100
                : 0;

            return response()->json([
                'status' => true,
                'data' => ['goal' => $goal]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Goal not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified goal.
     */
    public function update(Request $request, $id)
    {
        try {
            $goal = $request->user()->goals()->findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'target_amount' => 'required|numeric|min:0',
                'target_date' => 'required|date|after:today',
                'yearly_interest_rate' => 'nullable|numeric|min:0|max:100',
                'description' => 'nullable|string|max:1000',
                'projections' => 'required|array|min:1',
                'projections.*.description' => 'required|string|max:255',
                'projections.*.amount' => 'required|numeric',
                'projections.*.type' => 'required|in:income,expense',
                'projections.*.frequency' => 'required|in:once,monthly',
                'projections.*.date' => 'required_if:projections.*.frequency,once|nullable|date',
                'projections.*.start_date' => 'required_if:projections.*.frequency,monthly|nullable|date',
                'projections.*.end_date' => 'required_if:projections.*.frequency,monthly|nullable|date|after_or_equal:projections.*.start_date',
            ]);

            $goal->update([
                'name' => $validated['name'],
                'target_amount' => $validated['target_amount'],
                'target_date' => $validated['target_date'],
                'yearly_interest_rate' => $validated['yearly_interest_rate'] ?? 0,
                'description' => $validated['description'] ?? null,
            ]);

            // Update projections
            $goal->projections()->delete();
            $this->createProjections($goal, $validated['projections']);

            return response()->json([
                'status' => true,
                'message' => 'Goal updated successfully',
                'data' => ['goal' => $goal->load('projections')]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified goal.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $goal = $request->user()->goals()->findOrFail($id);

            $goal->projections()->delete();
            $goal->transactions()->delete();
            $goal->delete();

            return response()->json([
                'status' => true,
                'message' => 'Goal deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export goals data as CSV
     */
    public function export(Request $request)
    {
        try {
            $goals = $request->user()->goals()->with(['projections', 'transactions'])->get();

            $csvData = [];
            $csvData[] = ['Goal Name', 'Target Amount', 'Current Balance', 'Target Date', 'Interest Rate', 'Progress %', 'Status'];

            foreach ($goals as $goal) {
                $currentBalance = $goal->transactions()->sum('amount');
                $progressPercentage = $goal->target_amount > 0 ? ($currentBalance / $goal->target_amount) * 100 : 0;
                $status = $currentBalance >= $goal->target_amount ? 'Completed' : 'In Progress';

                $csvData[] = [
                    $goal->name,
                    number_format($goal->target_amount, 2),
                    number_format($currentBalance, 2),
                    Carbon::parse($goal->target_date)->format('Y-m-d'),
                    $goal->yearly_interest_rate . '%',
                    round($progressPercentage, 2) . '%',
                    $status
                ];
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'data' => $csvData,
                    'filename' => 'goals-export-' . date('Y-m-d') . '.csv'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to export goals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create projections for a goal based on frequency.
     */
    private function createProjections(Goal $goal, array $projections)
    {
        foreach ($projections as $proj) {
            $amount = $proj['type'] === 'expense' ? -$proj['amount'] : $proj['amount'];

            if ($proj['frequency'] === 'once') {
                $goal->projections()->create([
                    'description' => $proj['description'],
                    'amount' => $amount,
                    'projection_date' => $proj['date'],
                ]);
            } elseif ($proj['frequency'] === 'monthly') {
                $start = Carbon::parse($proj['start_date']);
                $end = Carbon::parse($proj['end_date']);

                $dayOfMonth = $start->day;
                $current = $start->copy();

                while ($current->lte($end)) {
                    $projectionDate = $current->copy()->day(min($dayOfMonth, $current->daysInMonth));

                    $goal->projections()->create([
                        'description' => $proj['description'],
                        'amount' => $amount,
                        'projection_date' => $projectionDate->format('Y-m-d'),
                    ]);

                    $current->addMonth();
                }
            }
        }
    }
}