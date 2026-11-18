<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HistoricalOrderSeeder extends Seeder
{
    public function run()
    {
        $employeeId = User::where('role', User::ROLE_EMPLOYEE)->first()?->id ?? User::first()?->id ?? 1;
        $products = Product::all();

        $startDate = Carbon::now()->subYears(2)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $this->command->info('Starting historical data generation (50+ per product per day)...');

        foreach ($products as $product) {
            $this->command->info("Seeding orders for product: {$product->name}");
            
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $ordersData = [];
                $orderItemsData = [];
                
                // Generate at least 50 orders for this specific product on this specific day
                $dailyCount = rand(50, 60); 

                for ($i = 0; $i < $dailyCount; $i++) {
                    $randomTime = $currentDate->copy()
                        ->setHour(rand(8, 20))
                        ->setMinute(rand(0, 59))
                        ->setSecond(rand(0, 59));

                    if ($randomTime->isFuture()) {
                        $randomTime = Carbon::now();
                    }

                    $ordersData[] = [
                        'employee_id' => $employeeId,
                        'status' => 'CONFIRMED',
                        'created_at' => $randomTime,
                        'updated_at' => $randomTime,
                    ];
                }

                // Chunk insert orders for performance
                foreach (array_chunk($ordersData, 500) as $chunk) {
                    DB::table('orders')->insert($chunk);
                }

                // Fetch the newly inserted order IDs to link their corresponding items
                $insertedOrders = DB::table('orders')
                    ->where('created_at', '>=', $currentDate->copy()->startOfDay())
                    ->where('created_at', '<=', $currentDate->copy()->endOfDay())
                    ->where('employee_id', $employeeId)
                    ->orderBy('id', 'desc')
                    ->limit(count($ordersData))
                    ->get();

                foreach ($insertedOrders as $order) {
                    $orderItemsData[] = [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'qty' => rand(1, 5),
                        'price' => $product->price,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ];
                }

                // Chunk insert order items
                foreach (array_chunk($orderItemsData, 500) as $chunk) {
                    DB::table('order_items')->insert($chunk);
                }

                // Move to the next calendar day
                $currentDate->addDay();
            }
        }

        $this->command->info('Historical order seeding completed successfully!');
    }
}