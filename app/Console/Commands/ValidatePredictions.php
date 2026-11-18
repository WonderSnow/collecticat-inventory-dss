<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prediction;
use App\Models\OrderItem;
use Carbon\Carbon;

class ValidatePredictions extends Command
{
    protected $signature = 'predictions:validate';
    protected $description = 'Evaluate past predictions against actual sales data';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting prediction validation...');

        // Find predictions for dates that have already passed
        $pastPredictions = Prediction::where('prediction_for', '<=', now()->toDateString())->get();

        if ($pastPredictions->isEmpty()) {
            $this->warn('No past predictions found to validate.');
            return 0;
        }

        $totalError = 0;
        $count = 0;

        foreach ($pastPredictions as $pred) {
            // Fetch actual sales for that product on that specific interval/date
            $actualSales = OrderItem::where('product_id', $pred->product_id)
                ->whereDate('created_at', $pred->prediction_for)
                ->sum('qty');

            $predictedSales = $pred->sales_quantity;
            $absoluteError = abs($predictedSales - $actualSales);

            $totalError += $absoluteError;
            $count++;

            $this->line("Product ID: {$pred->product_id} | Date: {$pred->prediction_for} | Predicted: " . round($predictedSales, 2) . " | Actual: {$actualSales} | Error: " . round($absoluteError, 2));
        }

        $mae = $count > 0 ? $totalError / $count : 0;
        $this->info("---------------------------------------------------");
        $this->info("Validation Complete. Mean Absolute Error (MAE): " . round($mae, 2));

        return 0;
    }
}