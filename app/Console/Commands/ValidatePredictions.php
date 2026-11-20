<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prediction;
use App\Models\OrderItem;
use Carbon\Carbon;

class ValidatePredictions extends Command
{
    protected $signature = 'predictions:validate {--confirmed-only : Only count items from CONFIRMED orders}';
    protected $description = 'Evaluate past sales predictions and stock recommendations against actual sales data';

    public function handle()
    {
        $this->info('Starting prediction validation...');

        $predictions = Prediction::orderBy('prediction_for')->get();
        $pastPredictions = $predictions->filter(function ($pred) {
            return $this->isIntervalCompleted($pred);
        });

        if ($pastPredictions->isEmpty()) {
            $this->warn('No completed past predictions found to validate.');
            return 0;
        }

        $totalAbsError = 0;
        $totalSquaredError = 0;
        $totalSignedError = 0; // positive = over-predicted, negative = under-predicted
        $totalActual = 0;
        $underPredictedCount = 0;

        $stockCoveredCount = 0;
        $totalShortfall = 0;
        $totalExcessStock = 0;
        $count = 0;

        foreach ($pastPredictions as $pred) {
            $actualSales = (float) $this->getActualSales($pred);
            $predictedSales = (float) $pred->sales_quantity;
            $recommendedStock = (float) ($pred->stock_recommendation ?? 0);

            $signedError = $predictedSales - $actualSales;
            $absError = abs($signedError);
            $squaredError = $signedError ** 2;

            if ($actualSales > $predictedSales) {
                $underPredictedCount++;
            }

            // Stock recommendation evaluation
            $shortfall = max(0.0, $actualSales - $recommendedStock);
            $excessStock = max(0.0, $recommendedStock - $actualSales);

            if ($recommendedStock >= $actualSales) {
                $stockCoveredCount++;
            }

            $totalAbsError += $absError;
            $totalSquaredError += $squaredError;
            $totalSignedError += $signedError;
            $totalActual += $actualSales;
            $totalShortfall += $shortfall;
            $totalExcessStock += $excessStock;
            $count++;

            $this->line(
                "Product ID: {$pred->product_id} | Period ({$pred->interval}): {$pred->prediction_for} | " .
                "Pred: " . round($predictedSales, 2) . " | Act: " . round($actualSales, 2) .
                " | Rec Stock: " . round($recommendedStock, 0) .
                " | Shortfall: " . round($shortfall, 0) . " | Excess: " . round($excessStock, 0)
            );
        }

        $mae = $count > 0 ? $totalAbsError / $count : 0;
        $rmse = $count > 0 ? sqrt($totalSquaredError / $count) : 0;
        $bias = $count > 0 ? $totalSignedError / $count : 0;
        $wape = $totalActual > 0 ? ($totalAbsError / $totalActual) * 100 : 0;
        $underPredRate = $count > 0 ? ($underPredictedCount / $count) * 100 : 0;

        $stockCoverageRate = $count > 0 ? ($stockCoveredCount / $count) * 100 : 0;
        $avgShortfall = $count > 0 ? $totalShortfall / $count : 0;
        $avgExcessStock = $count > 0 ? $totalExcessStock / $count : 0;

        $this->info('-------------------------------------------------------------------------');
        $this->info('Evaluated Predictions     : ' . $count);
        $this->info('MAE (Mean Abs Error)      : ' . round($mae, 2));
        $this->info('RMSE                      : ' . round($rmse, 2));
        $this->info('Bias (Pred - Actual)      : ' . round($bias, 2));
        $this->info('WAPE                      : ' . round($wape, 2) . '%');
        $this->info('Under-Prediction Rate     : ' . round($underPredRate, 2) . '% (Actual > Predicted)');
        $this->info('-------------------------------------------------------------------------');
        $this->info('Stock Coverage Rate       : ' . round($stockCoverageRate, 2) . '% (Rec Stock >= Actual)');
        $this->info('Total / Avg Stock Shortfall: ' . round($totalShortfall, 0) . ' / ' . round($avgShortfall, 2));
        $this->info('Total / Avg Excess Stock  : ' . round($totalExcessStock, 0) . ' / ' . round($avgExcessStock, 2));

        return 0;
    }

    private function isIntervalCompleted(Prediction $pred): bool
    {
        $now = Carbon::now();

        return match ($pred->interval) {
            'day'   => Carbon::parse($pred->prediction_for)->lt($now->copy()->startOfDay()),
            'month' => Carbon::createFromFormat('Y-m', $pred->prediction_for)->startOfMonth()->lt($now->copy()->startOfMonth()),
            'year'  => (int) $pred->prediction_for < (int) $now->year,
            default => Carbon::parse($pred->prediction_for)->lte($now->toDateString()),
        };
    }

    private function getActualSales(Prediction $pred)
    {
        $query = OrderItem::query()
            ->where('order_items.product_id', $pred->product_id);

        if ($this->option('confirmed-only')) {
            $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                  ->where('orders.status', \App\Models\Order::STATUS_CONFIRMED);
        }

        switch ($pred->interval) {
            case 'month':
                [$year, $month] = explode('-', $pred->prediction_for);
                $query->whereYear('order_items.created_at', (int) $year)
                      ->whereMonth('order_items.created_at', (int) $month);
                break;

            case 'year':
                $query->whereYear('order_items.created_at', (int) $pred->prediction_for);
                break;

            case 'day':
            default:
                $query->whereDate('order_items.created_at', $pred->prediction_for);
                break;
        }

        return $query->sum('order_items.qty');
    }
}