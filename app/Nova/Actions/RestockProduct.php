<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;

class RestockProduct extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Increase Current Stock';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $amount = (int) $fields->quantity;

        foreach ($models as $model) {
            // Adjust 'current_stock' or your corresponding stock column name
            $model->increment('current_stock', $amount);
        }

        return Action::message("Successfully added {$amount} items to stock for selected products!");
    }

    /**
     * Get the fields available on the action modal.
     *
     * @return array
     */
    public function fields()
    {
        return [
            Number::make('Quantity to Add', 'quantity')
                ->rules('required', 'integer', 'min:1')
                ->default(1)
                ->help('Enter the number of stock units to add to each selected product.'),
        ];
    }
}