<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Http\Requests\NovaRequest;

class DailyQuota extends Value
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $user = $request->user();

        $orderToday = \App\Models\Order::whereStatus(\App\Models\Order::STATUS_CONFIRMED)
            ->whereEmployeeId($user->id)
            ->whereDate('created_at', now())
            ->count();

        $quota = $user->quota ?? 0;
        $percentage = $quota > 0 ? round(($orderToday / $quota) * 100) : 0;

        return $this->result("{$percentage}%")
            ->suffix("Quota: {$quota} | Sold: {$orderToday}");
    }

    /**
     * Get the displayable name of the metric.
     *
     * @return string
     */
    public function name()
    {
        return 'Daily Quota';
    }
}