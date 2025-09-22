<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function index()
    {
        try {
            $debts = Debt::where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'debts' => $debts,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch debts',
                'data' => [
                    'debts' => [],
                ]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'initial_amount' => 'required|numeric|min:0',
            'target_payoff_date' => 'nullable|date',
            'yearly_interest_rate' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['user_id'] = auth()->id();

        $debt = Debt::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Debt created successfully',
            'debt' => $debt,
        ], 201);
    }

    public function show(Debt $debt)
    {
        // Ensure user can only view their own debts
        if ($debt->user_id !== auth()->id()) {
            return response()->json(['message' => 'Debt not found'], 404);
        }

        return response()->json($debt);
    }

    public function update(Request $request, Debt $debt)
    {
        // Ensure user can only update their own debts
        if ($debt->user_id !== auth()->id()) {
            return response()->json(['message' => 'Debt not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'initial_amount' => 'required|numeric|min:0',
            'target_payoff_date' => 'nullable|date',
            'yearly_interest_rate' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $debt->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Debt updated successfully',
            'debt' => $debt,
        ]);
    }

    public function destroy(Debt $debt)
    {
        // Ensure user can only delete their own debts
        if ($debt->user_id !== auth()->id()) {
            return response()->json(['message' => 'Debt not found'], 404);
        }

        $debt->delete();

        return response()->json([
            'success' => true,
            'message' => 'Debt deleted successfully',
        ]);
    }
}