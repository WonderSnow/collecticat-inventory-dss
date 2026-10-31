<?php

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\ApiAuthenticationController;
use App\Models\CronJob;
use App\Models\Endpoint;
use App\Models\Order;
use App\Models\OrderItem;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Private access
Route::middleware("auth:sanctum")->group(function () {
    Route::get("/auth-test", function () {
        return "authentication test";
    });
    Route::post("/logout", [ApiAuthenticationController::class, "logout"]);
});

Route::get("/public-test", function () {
    return "public test";
});

// User authentication
Route::post("/register", [ApiAuthenticationController::class, "register"]);
Route::post("/login", [ApiAuthenticationController::class, "login"]);

Route::any("/cron", function (Request $request) {
    CronJob::create([]);
});

Route::any("/v1/{params}", function (Request $request, $params) {
    $method = Str::lower($request->getMethod());
    $path = $request->getPathInfo();
    $arr_path = explode("/", $path);
    $name = end($arr_path);
    $endpoint = Endpoint::whereMethod($method)->wherePath($name)->first();
    return [
        "params" => $endpoint,
        "method" => Str::lower($request->getMethod()),
    ];
});

Route::get("/test", function () {
    $rows = app(\App\Services\GoogleSheetService::class)->getRows("A2:K");

    return $rows;
});

Route::get('/product-trend/{product}/{interval}', [\App\Http\Controllers\ProductController::class, 'trendData']);

Route::get('/system-order-trend', function (Request $request) {
    $range = $request->query('range', 'year');
    $tz = 'Asia/Manila';
    $now = Carbon::now($tz);

    // ----------------------------------------------------
    // YEARLY FILTER (Dropdown value: 'today')
    // ----------------------------------------------------
    if ($range === 'today' || $range === 'year') {
        // Find the earliest and latest confirmed orders to establish the dynamic year range
        $earliestOrder = Order::where('status', Order::STATUS_CONFIRMED)
            ->orderBy('created_at', 'asc')
            ->first();

        $latestOrder = Order::where('status', Order::STATUS_CONFIRMED)
            ->orderBy('created_at', 'desc')
            ->first();

        $startYear = $earliestOrder ? Carbon::parse($earliestOrder->created_at)->timezone($tz)->year : $now->year;
        $maxOrderYear = $latestOrder ? Carbon::parse($latestOrder->created_at)->timezone($tz)->year : $now->year;
        
        // End year should be at least the current year, or extend further if there are newer orders
        $endYear = max($now->year, $maxOrderYear, $startYear);

        $labels = [];
        $sales = [];

        for ($y = $startYear; $y <= $endYear; $y++) {
            $labels[] = (string)$y;
            
            // Count confirmed orders specifically for this year
            $count = Order::where('status', Order::STATUS_CONFIRMED)
                ->whereYear('created_at', $y)
                ->count();
                
            $sales[] = $count;
        }

        return response()->json([
            'type'   => 'standard',
            'labels' => $labels,
            'sales'  => $sales,
        ]);
    }

    // ----------------------------------------------------
    // 2. THIS WEEK FILTER (Sunday - Saturday)
    // ----------------------------------------------------
    if ($range === 'week') {
        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $sales = array_fill(0, 7, 0);

        $startOfWeek = $now->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $endOfWeek   = $now->copy()->endOfWeek(Carbon::SATURDAY)->endOfDay();

        $orders = Order::where('status', Order::STATUS_CONFIRMED)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->get();

        foreach ($orders as $order) {
            $localTime = Carbon::parse($order->created_at)->timezone($tz);
            $dayOfWeek = (int)$localTime->format('w');
            if (isset($sales[$dayOfWeek])) {
                $sales[$dayOfWeek]++;
            }
        }

        return response()->json([
            'type'   => 'standard',
            'labels' => $labels,
            'sales'  => $sales,
        ]);
    }

    // ----------------------------------------------------
    // 3. THIS MONTH FILTER (Days 01 to End of Month)
    // ----------------------------------------------------
    $daysInMonth = $now->daysInMonth;
    $labels = [];
    $sales = array_fill(0, $daysInMonth, 0);

    for ($i = 1; $i <= $daysInMonth; $i++) {
        $labels[] = str_pad($i, 2, '0', STR_PAD_LEFT);
    }

    $startOfMonth = $now->copy()->startOfMonth()->startOfDay();
    $endOfMonth   = $now->copy()->endOfMonth()->endOfDay();

    $orders = Order::where('status', Order::STATUS_CONFIRMED)
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
        ->get();

    foreach ($orders as $order) {
        $localTime = Carbon::parse($order->created_at)->timezone($tz);
        $day = (int)$localTime->format('j');
        $index = $day - 1;
        if (isset($sales[$index])) {
            $sales[$index]++;
        }
    }

    return response()->json([
        'type'   => 'standard',
        'labels' => $labels,
        'sales'  => $sales,
    ]);
});