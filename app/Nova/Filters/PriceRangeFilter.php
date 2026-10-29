<?php

namespace App\Nova\Filters;

use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;

class PriceRangeFilter extends Filter
{
    public function name()
    {
        return 'Price Range (₱)';
    }

    public function apply(Request $request, $query, $value)
    {
        switch ($value) {
            case 'under-100':
                return $query->where('price', '<', 100);
            case '100-1000':
                return $query->whereBetween('price', [100, 1000]);
            case '1001-10000':
                return $query->whereBetween('price', [1001, 10000]);
            case 'over-10000':
                return $query->where('price', '>', 10000);
            default:
                return $query;
        }
    }

    public function options(Request $request)
    {
        return [
            'Under ₱100' => 'under-100',
            '₱100 - ₱1,000' => '100-1000',
            '₱1,001 - ₱10,000' => '1001-10000',
            'Over ₱10,000' => 'over-10000',
        ];
    }
}