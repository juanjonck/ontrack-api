<?php 
    // app/Services/GoalProjectionService.php
    namespace App\Services;
    use App\Models\Goal;
    use Illuminate\Support\Carbon;

    class GoalProjectionService
    {
        public function calculate(Goal $goal): array
        {
            $currentBalance = $goal->transactions()->sum('amount');
            $projectedBalance = $currentBalance;
            $startDate = Carbon::today()->startOfMonth();
            $endDate = Carbon::parse($goal->target_date);

            // --- Analyze past performance ---
            $reasons = [];
            $variance = 0;

            // Get all projections that have passed and are not 'planned'
            $pastProjections = $goal->projections()
                ->where('projection_date', '<', Carbon::today())
                ->where('status', '!=', 'planned')
                ->with('goal.transactions') // Eager load for efficiency
                ->get();

            foreach ($pastProjections as $proj) {
                if ($proj->status === 'missed') {
                    $variance += $proj->amount; // A missed income is a negative variance, a missed expense is a positive one
                    $reasons['missed'] = ($reasons['missed'] ?? 0) + 1;
                } elseif ($proj->status === 'reconciled') {
                    // Find the transaction linked to this projection
                    $transaction = $goal->transactions()->where('planned_projection_id', $proj->id)->first();
                    $actualAmount = $transaction ? $transaction->amount : 0;
                    $diff = $proj->amount - $actualAmount;
                    if (abs($diff) > 0.01) {
                        $variance += $diff;
                        $reasons['shortfall'] = ($reasons['shortfall'] ?? 0) + 1;
                    }
                }
            }

            // Apply the variance from past performance to the projected balance
            $projectedBalance -= $variance; // Subtract the negative impact of missed/short payments

            // Loop for future projections remains the same
            for ($date = $startDate; $date->lte($endDate); $date->addMonth()) {
                if ($goal->yearly_interest_rate > 0) {
                    $monthlyInterest = ($projectedBalance * ($goal->yearly_interest_rate / 100)) / 12;
                    $projectedBalance += $monthlyInterest;
                }

                $monthlyProjections = $goal->projections()
                    ->where('status', 'planned')
                    ->whereYear('projection_date', $date->year)
                    ->whereMonth('projection_date', $date->month)
                    ->sum('amount');

                $projectedBalance += $monthlyProjections;
            }

            $isOnTrack = $projectedBalance >= $goal->target_amount;
            $shortfall = !$isOnTrack ? $goal->target_amount - $projectedBalance : 0;

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
                $analysisMessage = "This goal may be off track due to " . implode(' and ', $reasonParts) . ". The total shortfall from past events is $" . number_format(abs($variance), 2) . ".";
            }

            return [
                'projected_balance' => round($projectedBalance, 2),
                'is_on_track' => $isOnTrack,
                'shortfall' => round($shortfall, 2),
                'current_balance' => round($currentBalance, 2),
                'analysis_message' => $analysisMessage,
            ];
        }

        public function getMonthlyProjectionData(Goal $goal): array
        {
            $currentBalance = $goal->transactions()->sum('amount');
            $projectedBalance = $currentBalance;
            
            // Also apply past variance to chart data
            $variance = 0;
            $pastProjections = $goal->projections()
                ->where('projection_date', '<', Carbon::today())
                ->where('status', '!=', 'planned')
                ->with('goal.transactions')
                ->get();

            foreach ($pastProjections as $proj) {
                if ($proj->status === 'missed') {
                    $variance += $proj->amount;
                } elseif ($proj->status === 'reconciled') {
                    $transaction = $goal->transactions()->where('planned_projection_id', $proj->id)->first();
                    $actualAmount = $transaction ? $transaction->amount : 0;
                    $diff = $proj->amount - $actualAmount;
                    if (abs($diff) > 0.01) {
                        $variance += $diff;
                    }
                }
            }

            $projectedBalance -= $variance; // Apply variance to chart data too
            
            $startDate = Carbon::today()->startOfMonth();
            $endDate = Carbon::parse($goal->target_date);

            $labels = [];
            $data = [];

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addMonth()) {
                if ($goal->yearly_interest_rate > 0) {
                    $monthlyInterest = ($projectedBalance * ($goal->yearly_interest_rate / 100)) / 12;
                    $projectedBalance += $monthlyInterest;
                }

                $monthlyProjections = $goal->projections()
                    ->where('status', 'planned')
                    ->whereYear('projection_date', $date->year)
                    ->whereMonth('projection_date', $date->month)
                    ->sum('amount');

                $projectedBalance += $monthlyProjections;
                
                $labels[] = $date->format('M Y');
                $data[] = round($projectedBalance, 2);
            }

            return [
                'labels' => $labels,
                'data' => $data,
            ];
        }
    }
?>