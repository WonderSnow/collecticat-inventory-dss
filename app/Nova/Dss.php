<?php

namespace App\Nova;

use App\Nova\Actions\AddToCart;
use App\Nova\Actions\RemoveToCart;
use App\Nova\Actions\RunInventoryForecast;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Sparkline;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Actions\RestockProduct;

class Dss extends Resource
{
    public static $group = "";
    public static function availableForNavigation(Request $request)
    {
        return auth()->user()->role === \App\Models\User::ROLE_ADMIN;
    }
    public static function label () {
        return "Inventory";
    }
    public static function authorizedToCreate(Request $request)
    {
        return false;
    }
    public function authorizedToUpdate(Request $request)
{
    // Allow Nova actions for all authorized users
    if (auth()->user()->role === \App\Models\User::ROLE_ADMIN) {
        return true;
    }

    // Otherwise forbid standard inline updates for Admin
    if (auth()->user()->role === \App\Models\User::ROLE_ADMIN) {
        return false;
    }

    return false;
}

    public function authorizedToDelete(Request $request)
    {
        if ($request->has('action')) return true;
        return false;
    }
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Product::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */

    public function title() {
        return "$this->name (Category: $this->category, Price: $this->price)";
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'name',
        'category',
        'search_keyword',
        'card_set_category'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            Sparkline::make('Sales Trend')
    ->hideFromIndex()
    ->hideFromDetail()
    ->data(function () {
        $results = $this->orderItems()
            ->whereHas('order', function ($query) {
                $query->where('status', \App\Models\Order::STATUS_CONFIRMED);
            })
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->pluck('count')
            ->toArray();

        return $results;
    }),
            Text::make('Category', 'card_set_category')->rules(['required'])->sortable(),
            Text::make('Name', function () {

    $hasSales = $this->orderItems()
        ->whereHas('order', function ($query) {
            $query->where('status', \App\Models\Order::STATUS_CONFIRMED);
        })
        ->exists();

    if ($hasSales) {
        return "<a href='/demand-frequency/{$this->id}' class='text-primary font-bold'>$this->name</a>";
    }

    return $this->name;

})
->asHtml()
->sortable()
->rules('required', 'max:255'),
            Currency::make('Price')
                ->sortable()
                ->rules('required', 'min:0'),
            Number::make('Default Stock', 'default_stock')
                ->sortable()
                ->rules('required', 'min:0'),
            Number::make('Current Stock', 'current_stock')
                ->sortable()
                ->rules('required', 'min:0'),
            Text::make('Predictions (P/R)', function () {

    $intervals = [
        'day' => 'Next Day',
        'week' => 'Next Week',
        'month' => 'Next Month',
        'year' => 'Next Year',
    ];

    $data = [];

    foreach ($intervals as $key => $label) {
        $prediction = $this->predictions()->where('interval', $key)->latest()->first();

        if (! $prediction) {
            $data[$key] = "<span class='text-warning text-xs'>Insufficient Data.</span>";
        } else {
            $sales = $prediction->sales_quantity;
            $rec = $prediction->stock_recommendation;
            $s = $rec > 0 ? '+' : '';
            $data[$key] = "<span>{$sales} / {$s}{$rec}</span>";
        }
    }

    $jsonData = htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8');
    $id = "pred-{$this->id}";

    return "
        <div style='min-width:150px'>

            <select 
                onchange='
                    var data = JSON.parse(this.dataset.pred);
                    document.getElementById(\"{$id}\").innerHTML = data[this.value];
                '
                data-pred='{$jsonData}'
                style='width:100% !important; font-size:12px !important; margin-bottom:4px !important; padding:4px !important; background-color: #000000 !important; color: #ffffff !important; border: 1px solid #374151 !important; border-radius:4px !important;'>
                <option value='day' style='background-color: #000000; color: #ffffff;'>Next Day</option>
                <option value='week' style='background-color: #000000; color: #ffffff;'>Next Week</option>
                <option value='month' style='background-color: #000000; color: #ffffff;'>Next Month</option>
                <option value='year' style='background-color: #000000; color: #ffffff;'>Next Year</option>
            </select>

            <div id='{$id}' class='dss-prediction-text' style='text-align: center !important; font-size: 16px !important;'>
                {$data['day']}
            </div>

        </div>
    ";
})->asHtml(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [
            new \App\Nova\Filters\PriceRangeFilter,
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [
            RunInventoryForecast::make()->standalone(),
            new RestockProduct,
        ];
    }
}
