<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Support\Facades\DB;

class MarkAsConfirmed extends Action
{
    use InteractsWithQueue, Queueable;

    public function shownOnTableRow()
    {
        return true;
    }

    public $name = 'Mark as Confirmed';

    public $confirmText = 'Would you like to confirm the order?';

    public $confirmButtonText = 'Continue';

    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $order) {

            // Prevent confirming twice
            if ($order->status === \App\Models\Order::STATUS_CONFIRMED) {
                return Action::danger('Order already confirmed.');
            }

            $order->status = \App\Models\Order::STATUS_CONFIRMED;
            $order->save();

            // Deduct product stock and log as 'sold' for the audit trail
            foreach ($order->orderItems as $item) {
                $product = $item->product;

                if ($product) {
                    $product->auditAction = 'sold';
                    $product->current_stock -= $item->quantity; // Change 'quantity' if your column is named differently
                    $product->save();
                }
            }
        }

        return Action::message('Order confirmed successfully.');
    }

    public function fields()
    {
        return [];
    }
}