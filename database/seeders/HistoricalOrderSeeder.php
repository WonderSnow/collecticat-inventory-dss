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

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Please seed products first.');
            return;
        }

        $this->command->info('Starting dense historical order generation...');

        // Adjust these to control how dense your sales history is:
        $daysToCover = 90;            // Shorter window = higher daily density (e.g., 90 days instead of 2 years)
        $ordersPerDayMin = 15;
        $ordersPerDayMax = 25;

        // Optional: focus on the first N products (or all products if count is small)
        // so specific test products get frequent sales instead of being diluted across a huge catalog.
        $activeProducts = $products->count() > 20 ? $products->take(20) : $products;

        $ordersData = [];
        $now = Carbon::now();

        for ($dayOffset = 0; $dayOffset < $daysToCover; $dayOffset++) {
            $date = $now->copy()->subDays($dayOffset)->startOfDay();
            $dailyOrdersCount = rand($ordersPerDayMin, $ordersPerDayMax);

            for ($i = 0; $i < $dailyOrdersCount; $i++) {
                $randomTime = $date->copy()
                    ->setHour(rand(8, 20))
                    ->setMinute(rand(0, 59))
                    ->setSecond(rand(0, 59));

                if ($randomTime->gt($now)) {
                    $randomTime = $now->copy();
                }

                $ordersData[] = [
                    'employee_id' => $employeeId,
                    'status' => 'CONFIRMED',
                    'created_at' => $randomTime,
                    'updated_at' => $randomTime,
                ];
            }
        }

        $this->command->info('Inserting ' . count($ordersData) . ' orders and capturing IDs...');

        $orderIds = [];
        foreach (array_chunk($ordersData, 500) as $chunk) {
            foreach ($chunk as $order) {
                $orderId = DB::table('orders')->insertGetId($order);
                $orderIds[] = [
                    'id' => $orderId,
                    'created_at' => $order['created_at'],
                ];
            }
        }

        $this->command->info('Generating and attaching order items...');

        $orderItemsData = [];
        foreach ($orderIds as $order) {
            $basketSize = rand(1, min(4, $activeProducts->count()));
            $selectedProducts = $activeProducts->random($basketSize);

            foreach ($selectedProducts as $product) {
                $orderItemsData[] = [
                    'order_id' => $order['id'],
                    'product_id' => $product->id,
                    'qty' => rand(1, 4),
                    'price' => $product->price,
                    'created_at' => $order['created_at'],
                    'updated_at' => $order['created_at'],
                ];
            }

            if (count($orderItemsData) >= 500) {
                DB::table('order_items')->insert($orderItemsData);
                $orderItemsData = [];
            }
        }

        if (!empty($orderItemsData)) {
            DB::table('order_items')->insert($orderItemsData);
        }

        $this->command->info('Dense historical order seeding completed successfully!');
    }
}