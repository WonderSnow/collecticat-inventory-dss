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

        // Target: 100 historical orders per product per year over a 2-year span (200 total per product)
        $ordersPerYear = 100;
        $yearsToCover = 2;
        $totalOrdersPerProduct = $ordersPerYear * $yearsToCover;

        $this->command->info("Starting historical data generation ({$ordersPerYear} per product per year)...");

        foreach ($products as $product) {
            $this->command->info("Seeding orders for product: {$product->name}");
            
            $ordersData = [];
            $orderItemsData = [];

            for ($i = 0; $i < $totalOrdersPerProduct; $i++) {
                // Pick a random timestamp within the last 2 years
                $randomTime = Carbon::now()
                    ->subYears($yearsToCover)
                    ->addSeconds(rand(0, $yearsToCover * 365 * 24 * 60 * 60))
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

            // Bulk insert orders in chunks of 500 for performance
            foreach (array_chunk($ordersData, 500) as $chunk) {
                DB::table('orders')->insert($chunk);
            }

            // Fetch the newly inserted orders for this product run to map order items
            // We fetch orders created within the last 2 years that match our employee ID
            $insertedOrders = DB::table('orders')
                ->where('employee_id', $employeeId)
                ->where('created_at', '>=', Carbon::now()->subYears($yearsToCover))
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

            // Bulk insert order items in chunks of 500
            foreach (array_chunk($orderItemsData, 500) as $chunk) {
                DB::table('order_items')->insert($chunk);
            }
        }

        $this->command->info('Historical order seeding completed successfully!');
    }
}