<?php

namespace App\Nova\Filters;

use Illuminate\Http\Request; // <-- Make sure this is imported
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class UserStatusFilter extends Filter
{
    /**
     * Get the filter's available options.
     *
     * @param  \Illuminate\Http\Request  $request  // <-- Updated type hint
     * @return array
     */
    public function options(Request $request) // <-- Changed from NovaRequest to Request
    {
        return [
            'Active' => 'ACTIVE',
            'Archived' => 'ARCHIVE',
        ];
    }

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Request $request, $query, $value)
    {
        return $query->where('status', $value);
    }
}