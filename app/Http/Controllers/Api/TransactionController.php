<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $year = $request->input('year', Carbon::now()->year);
            $month = $request->input('month', Carbon::now()->month);
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $transactions = $user->transactions()
                ->with(['category.parent'])
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->latest('transaction_date')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'transactions' => $transactions,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch transactions',
                'data' => [
                    'transactions' => []
                ]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'transaction_date' => 'required|date',
        ]);

        $category = Category::findOrFail($validated['category_id']);

        $transaction = $request->user()->transactions()->create([
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'category_id' => $category->id,
            'transaction_date' => $validated['transaction_date'],
        ]);

        return response()->json([
            'transaction' => $transaction->load('category'),
            'message' => 'Transaction created successfully'
        ], 201);
    }

    public function show(Transaction $transaction, Request $request)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'transaction' => $transaction->load('category')
            ]
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'transaction_date' => 'required|date',
        ]);

        $category = Category::findOrFail($validated['category_id']);

        $transaction->update([
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'category_id' => $category->id,
            'transaction_date' => $validated['transaction_date'],
        ]);

        return response()->json([
            'transaction' => $transaction->load('category'),
            'message' => 'Transaction updated successfully'
        ]);
    }

    public function destroy(Transaction $transaction, Request $request)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $transaction->delete();

        return response()->json([
            'message' => 'Transaction deleted successfully'
        ]);
    }

    public function split(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'splits' => 'required|array|min:2',
            'splits.*.description' => 'required|string|max:255',
            'splits.*.amount' => 'required|numeric|min:0.01',
            'splits.*.category_id' => 'required|exists:categories,id',
        ]);

        $splits = $request->input('splits');
        $totalSplitAmount = collect($splits)->sum('amount');
        $originalAmount = (float) $transaction->amount;

        if (abs($totalSplitAmount - $originalAmount) > 0.01) {
            return response()->json([
                'message' => 'Split amounts must total the original transaction amount.',
                'errors' => ['splits' => ['Split amounts must total the original transaction amount.']]
            ], 422);
        }

        try {
            \DB::beginTransaction();

            $originalDate = $transaction->transaction_date;
            $transaction->delete();

            $newTransactions = [];
            foreach ($splits as $split) {
                $category = Category::findOrFail($split['category_id']);

                $newTransaction = $request->user()->transactions()->create([
                    'description' => $split['description'],
                    'amount' => $split['amount'],
                    'category_id' => $category->id,
                    'transaction_date' => $originalDate,
                ]);

                $newTransactions[] = $newTransaction->load('category');
            }

            \DB::commit();

            return response()->json([
                'transactions' => $newTransactions,
                'message' => 'Transaction split successfully'
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'message' => 'Failed to split transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}