<?php 
// app/Services/DebtProjectionService.php
namespace App\Services;
use App\Models\Debt;
use Illuminate\Support\Carbon;

class DebtProjectionService
{
    public function calculate(Debt $debt): array
    {
        // Start with initial debt amount
        $currentBalance = $debt->initial_amount;
        
        // Apply all transactions to get current balance
        $transactionTotal = $debt->transactions()->sum('amount');
        $currentBalance -= $transactionTotal; // Subtract payments (positive amounts) from debt

        $projectedBalance = $currentBalance;
        $startDate = Carbon::today()->startOfMonth();
        $endDate = $debt->target_payoff_date ? Carbon::parse($debt->target_payoff_date) : Carbon::today()->addYear();

        // --- Analyze past performance ---
        $reasons = [];
        $variance = 0;

        // Get all projections that have passed and are not 'planned'
        $pastProjections = $debt->projections()
            ->where('projection_date', '<', Carbon::today())
            ->where('status', '!=', 'planned')
            ->with('debt.transactions')
            ->get();

        foreach ($pastProjections as $proj) {
            if ($proj->status === 'missed') {
                // Missed payments increase debt (negative variance for debt payoff)
                $variance -= $proj->amount;
                $reasons['missed'] = ($reasons['missed'] ?? 0) + 1;
            } elseif ($proj->status === 'reconciled') {
                $transaction = $debt->transactions()->where('planned_projection_id', $proj->id)->first();
                $actualAmount = $transaction ? $transaction->amount : 0;
                $diff = $proj->amount - $actualAmount;
                if (abs($diff) > 0.01) {
                    $variance -= $diff; // Lower payments than planned hurt debt payoff
                    $reasons['shortfall'] = ($reasons['shortfall'] ?? 0) + 1;
                }
            }
        }

        // Apply variance to projected balance
        $projectedBalance += $variance; // Add positive variance (more debt) or subtract negative (less debt)

        // Loop for future projections
        for ($date = $startDate; $date->lte($endDate); $date->addMonth()) {
            if ($debt->yearly_interest_rate > 0) {
                $monthlyInterest = ($projectedBalance * ($debt->yearly_interest_rate / 100)) / 12;
                $projectedBalance += $monthlyInterest; // Interest increases debt
            }

            $monthlyProjections = $debt->projections()
                ->where('status', 'planned')
                ->whereYear('projection_date', $date->year)
                ->whereMonth('projection_date', $date->month)
                ->sum('amount');

            $projectedBalance -= $monthlyProjections; // Subtract planned payments
        }

        $isOnTrack = $projectedBalance <= 0; // Debt is paid off when balance is zero or negative
        $remainingDebt = $projectedBalance > 0 ? $projectedBalance : 0;
        $shortfall = !$isOnTrack ? $projectedBalance : 0; // Additional amount needed to pay off debt

        // --- Generate analysis message ---
        $analysisMessage = null;
        if (!$isOnTrack && (!empty($reasons))) {
            $reasonParts = [];
            if (isset($reasons['missed'])) {
                $reasonParts[] = "missing " . $reasons['missed'] . " scheduled payment(s)";
            }
            if (isset($reasons['shortfall'])) {
                $reasonParts[] = "having " . $reasons['shortfall'] . " payment(s) reconciled for less than planned";
            }
            $analysisMessage = "This debt payoff may be behind schedule due to " . implode(' and ', $reasonParts) . ". The total shortfall from past events is $" . number_format(abs($variance), 2) . ".";
        }

        return [
            'projected_balance' => round($projectedBalance, 2),
            'is_on_track' => $isOnTrack,
            'shortfall' => round($shortfall, 2),
            'remaining_debt' => round($remainingDebt, 2),
            'remaining_balance' => round($remainingDebt, 2), // Add for consistency with views
            'current_balance' => round($currentBalance, 2),
            'paid_off_amount' => round($debt->initial_amount - $currentBalance, 2),
            'progress_percentage' => $debt->initial_amount > 0 ? (($debt->initial_amount - $currentBalance) / $debt->initial_amount) * 100 : 0,
            'analysis_message' => $analysisMessage,
        ];
    }

    public function getMonthlyProjectionData(Debt $debt): array
    {
        $currentBalance = $debt->initial_amount;
        $transactionTotal = $debt->transactions()->sum('amount');
        $currentBalance -= $transactionTotal;
        $projectedBalance = $currentBalance;
        
        // Apply past variance to chart data
        $variance = 0;
        $pastProjections = $debt->projections()
            ->where('projection_date', '<', Carbon::today())
            ->where('status', '!=', 'planned')
            ->with('debt.transactions')
            ->get();

        foreach ($pastProjections as $proj) {
            if ($proj->status === 'missed') {
                $variance -= $proj->amount;
            } elseif ($proj->status === 'reconciled') {
                $transaction = $debt->transactions()->where('planned_projection_id', $proj->id)->first();
                $actualAmount = $transaction ? $transaction->amount : 0;
                $diff = $proj->amount - $actualAmount;
                if (abs($diff) > 0.01) {
                    $variance -= $diff;
                }
            }
        }

        $projectedBalance += $variance;
        
        $startDate = Carbon::today()->startOfMonth();
        $endDate = $debt->target_payoff_date ? Carbon::parse($debt->target_payoff_date) : Carbon::today()->addYear();

        $labels = [];
        $data = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addMonth()) {
            if ($debt->yearly_interest_rate > 0) {
                $monthlyInterest = ($projectedBalance * ($debt->yearly_interest_rate / 100)) / 12;
                $projectedBalance += $monthlyInterest;
            }

            $monthlyProjections = $debt->projections()
                ->where('status', 'planned')
                ->whereYear('projection_date', $date->year)
                ->whereMonth('projection_date', $date->month)
                ->sum('amount');

            $projectedBalance -= $monthlyProjections;
            
            $labels[] = $date->format('M Y');
            $data[] = round(max(0, $projectedBalance), 2); // Don't show negative debt
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }
}
?>