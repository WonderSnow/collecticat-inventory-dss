<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    const STATUS_CONFIRMED = 'CONFIRMED';
    const STATUS_PENDING = 'PENDING';
    const STATUS_CANCELED = 'CANCELED';

    protected $fillable = [
        'employee_id',
        'status',
    ];

    /**
     * Automatically handle stock restoration when an order is canceled.
     */
    protected static function booted()
    {
        static::updating(function ($order) {
            // Check if the status is changing TO 'CANCELED' from something else
            if ($order->isDirty('status') && $order->status === self::STATUS_CANCELED) {
                // Prevent restoring stock twice if it was already canceled
                $originalStatus = $order->getOriginal('status');
                if ($originalStatus !== self::STATUS_CANCELED) {
                    DB::transaction(function () use ($order) {
                        foreach ($order->orderItems as $item) {
                            if ($item->product) {
                                // Fallback safely to catch either 'qty' or 'quantity' column names
                                $quantityToRestore = $item->qty ?? $item->quantity ?? 0;

                                // Add the ordered quantity back to current stock incrementally
                                $item->product->increment('current_stock', $quantityToRestore);
                            }
                        }
                    });
                }
            }
        });
    }

    /**
     * Relationship to the User who created the order.
     */
    public function employee() {
        return $this->belongsTo(\App\Models\User::class, 'employee_id');
    }

    /**
     * Relationship to the individual items in the order.
     */
    public function orderItems()
    {
        return $this->hasMany(\App\Models\OrderItem::class);
    }

    /**
     * Calculate the total amount safely.
     */
    public function getTotalAmountAttribute()
    {
        return $this->orderItems()
            ->whereHas('product') 
            ->get()
            ->sum(function ($item) {
                $qty = $item->qty ?? $item->quantity ?? 0;
                return $qty * ($item->price ?? 0);
            });
    }
}