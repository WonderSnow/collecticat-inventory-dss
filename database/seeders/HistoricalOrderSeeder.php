<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\Carbon;

class HistoricalOrderSeeder extends Seeder
{
    public function run()
    {
        $employee = User::where('role', User::ROLE_EMPLOYEE)->first() ?? User::first();
        $products = Product::all();

        foreach ($products as $product) {
            $orderCount = rand(100, 150);

            for ($i = 0; $i < $orderCount; $i++) {
                $randomDay = Carbon::now()->subYears(2)
                    ->addDays(rand(0, 730))
                    ->setHour(rand(8, 20))
                    ->setMinute(rand(0, 59));

                // Prevent future dates if random days exceed current time
                if ($randomDay->isFuture()) {
                    $randomDay = Carbon::now();
                }

                $order = Order::create([
                    'employee_id' => $employee?->id ?? 1,
                    'status' => 'CONFIRMED',
                    'created_at' => $randomDay,
                    'updated_at' => $randomDay,
                ]);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'qty' => rand(1, 5),
                    'price' => $product->price,
                    'created_at' => $randomDay,
                    'updated_at' => $randomDay,
                ]);
            }
        }
    }
}