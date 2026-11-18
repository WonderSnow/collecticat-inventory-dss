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

        $this->command->info('Starting optimized historical order generation...');

        $yearsToCover = 2;
        $totalOrdersToGenerate = 2000; // Generates a robust, realistic pool of orders across 2 years

        $ordersData = [];
        for ($i = 0; $i < $totalOrdersToGenerate; $i++) {
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

        $this->command->info('Inserting orders and capturing IDs...');

        $orderIds = [];
        // Insert orders in chunks and safely capture their generated IDs
        foreach (array_chunk($ordersData, 500) as $chunk) {
            foreach ($chunk as $order) {
                $orderId = DB::table('orders')->insertGetId($order);
                $orderIds[] = [
                    'id' => $orderId,
                    'created_at' => $order['created_at']
                ];
            }
        }

        $this->command->info('Generating and attaching order items...');

        $orderItemsData = [];
        foreach ($orderIds as $order) {
            // Assign 1 to 4 random products per order to simulate real customer baskets
            $selectedProducts = $products->random(rand(1, min(4, $products->count())));

            foreach ($selectedProducts as $product) {
                $orderItemsData[] = [
                    'order_id' => $order['id'],
                    'product_id' => $product->id,
                    'qty' => rand(1, 3),
                    'price' => $product->price,
                    'created_at' => $order['created_at'],
                    'updated_at' => $order['created_at'],
                ];
            }

            // Bulk insert in chunks of 500 to optimize memory and prevent packet size limits
            if (count($orderItemsData) >= 500) {
                DB::table('order_items')->insert($orderItemsData);
                $orderItemsData = [];
            }
        }

        // Insert any leftover items
        if (!empty($orderItemsData)) {
            DB::table('order_items')->insert($orderItemsData);
        }

        $this->command->info('Historical order seeding completed successfully!');
    }
}