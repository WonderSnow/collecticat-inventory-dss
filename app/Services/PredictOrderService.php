<?php

namespace App\Services;

use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Prediction;
use Carbon\Carbon;
use Exception;
use Log;

class PredictOrderService
{
    /**
     * Predict future sales and provide stock recommendations.
     *
     * @param int $product_id
     * @param string $period ('day', 'week', 'month', 'year')
     * @param int $predict_intervals Number of future intervals to predict
     * @param int $safetyMargin Optional safety margin to avoid stockouts
     * @return array|null
     */
    public function predict($product_id, $period = 'day', $predict_intervals = 1, $safetyMargin = 10)
    {
        try {
            $product = Product::findOrFail($product_id);
            $alpha = $product->stockout ?? 0;

            // --- 1. FETCH SALES BASED ON PERIOD -------------------------
            $salesQuery = OrderItem::selectRaw("
                SUM(qty) as total_qty,
                CASE
                    WHEN '$period' = 'day' THEN DATE(created_at)
                    WHEN '$period' = 'week' THEN YEARWEEK(created_at)
                    WHEN '$period' = 'month' THEN DATE_FORMAT(created_at, '%Y-%m')
                    WHEN '$period' = 'year' THEN YEAR(created_at)
                END as period
            ")
            ->where('product_id', $product_id)
            ->groupBy('period')
            ->orderBy('period')
            ->get();

            if ($salesQuery->isEmpty()) {
                return null;
            }

            $start = $salesQuery->first()->period;
            $end   = $salesQuery->last()->period;

            // Map existing sales by period key for quick lookup
            $salesMap = $salesQuery->pluck('total_qty', 'period')->toArray();

            // --- 2. GENERATE ALL HISTORICAL & FUTURE INTERVALS ------------
            $historicalIntervals = $this->generateHistoricalIntervals($period, $start, $end);
            $futureIntervals = $this->generateFutureIntervals($period, $end, $predict_intervals);

            // --- 3. PREPARE DATA (WITH ZERO-FILLING) -------------------
            $_orders = [];

            foreach ($historicalIntervals as $intervalKey) {
                // Explicitly fill 0 if no sales occurred on this date/period
                $_orders[] = isset($salesMap[$intervalKey]) ? (float) $salesMap[$intervalKey] : 0.0;
            }

            // --- 4. MOVING AVERAGE CALCULATION -------------------------
            // Look at a recent window (e.g., last 7 intervals) to capture baseline demand smoothing
            $window = 7;
            $recentSales = array_slice($_orders, -$window);
            $avgSales = count($recentSales) > 0 ? (array_sum($recentSales) / count($recentSales)) : 0;

            // --- 5. PREDICT FUTURE INTERVALS ---------------------------
            $predictions = [];
            $actualSales = $_orders[count($_orders) - 1] ?? 0;

            for ($i = 0; $i < $predict_intervals; $i++) {
                
                // Use moving average baseline for future prediction
                $predicted_qty = $avgSales;

                // Lost demand heuristic
                $lostDemand = max(0, ($predicted_qty - $actualSales) * $alpha);
                
                // Requirement Planning
                $recommendedStock = max(0, ($predicted_qty + $lostDemand) - $product->current_stock + $safetyMargin);

                $predictions[] = [
                    'period' => $futureIntervals[$i],
                    'sales_quantity' => max(0, $predicted_qty), // Ensure non-negative sales
                    'stock_recommendation' => (int) $recommendedStock,
                ];
            }

            // --- 6. SAVE PREDICTIONS ----------------------------------
            $results = [];
            foreach ($predictions as $p) {
                $results[] = Prediction::updateOrCreate(
                    [
                        'product_id'     => $product_id,
                        'prediction_for' => $p['period'],
                        'interval'       => $period,
                    ],
                    [
                        'sales_quantity'       => $p['sales_quantity'],
                        'stock_recommendation' => $p['stock_recommendation'],
                    ]
                );
            }

            return $results;

        } catch (Exception $error) {
            Log::error('Prediction failed: ' . $error->getMessage());
            return null;
        }
    }

    /**
     * Generate all historical intervals between start and end dates inclusive.
     */
    private function generateHistoricalIntervals($period, $start, $end)
    {
        $intervals = [];

        if ($period === 'day') {
            $current = Carbon::parse($start);
            $endDate = Carbon::parse($end);
            while ($current->lte($endDate)) {
                $intervals[] = $current->toDateString();
                $current->addDay();
            }
        } elseif ($period === 'week') {
            $current = Carbon::parse($start . ' Sunday');
            $endDate = Carbon::parse($end . ' Sunday');
            while ($current->lte($endDate)) {
                $intervals[] = $current->format('oW');
                $current->addWeek();
            }
        } elseif ($period === 'month') {
            $current = Carbon::parse($start . '-01');
            $endDate = Carbon::parse($end . '-01');
            while ($current->lte($endDate)) {
                $intervals[] = $current->format('Y-m');
                $current->addMonth();
            }
        } elseif ($period === 'year') {
            $current = Carbon::createFromDate($start);
            $endDate = Carbon::createFromDate($end);
            while ($current->lte($endDate)) {
                $intervals[] = $current->format('Y');
                $current->addYear();
            }
        }

        return $intervals;
    }

    /**
     * Generate future prediction intervals after the end date.
     */
    private function generateFutureIntervals($period, $end, $predict_intervals)
    {
        $intervals = [];

        if ($period === 'day') {
            $current = Carbon::parse($end)->addDay();
            for ($i = 0; $i < $predict_intervals; $i++) {
                $intervals[] = $current->toDateString();
                $current->addDay();
            }
        } elseif ($period === 'week') {
            $current = Carbon::parse($end . ' Sunday')->addWeek();
            for ($i = 0; $i < $predict_intervals; $i++) {
                $intervals[] = $current->format('oW');
                $current->addWeek();
            }
        } elseif ($period === 'month') {
            $current = Carbon::parse($end . '-01')->addMonth();
            for ($i = 0; $i < $predict_intervals; $i++) {
                $intervals[] = $current->format('Y-m');
                $current->addMonth();
            }
        } elseif ($period === 'year') {
            $current = Carbon::createFromDate($end)->addYear();
            for ($i = 0; $i < $predict_intervals; $i++) {
                $intervals[] = $current->format('Y');
                $current->addYear();
            }
        }

        return $intervals;
    }
}