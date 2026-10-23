<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class ArchivedUser extends Resource
{
    public static $group = "Records";

    public static $model = \App\Models\User::class;

    public static $title = 'name';

    public static $search = [
        'id', 'name', 'email',
    ];

    public static function label()
    {
        return 'Archived Users';
    }

    public static function singularLabel()
    {
        return 'Archived User';
    }

    public static function availableForNavigation(Request $request)
    {
        return auth()->user()->role === \App\Models\User::ROLE_ADMIN;
    }

    // authorizedToCreate is static in Nova
    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    // authorizedToUpdate must be non-static
    public function authorizedToUpdate(Request $request)
    {
        return false;
    }

    // authorizedToDelete must be non-static
    public function authorizedToDelete(Request $request)
    {
        return false;
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->where('status', 'ARCHIVE');
    }

    public function fields(Request $request)
    {
        return [
            Select::make('Role')
                ->options([
                    \App\Models\User::ROLE_ADMIN => 'Administrator',
                    \App\Models\User::ROLE_EMPLOYEE => 'Employee',
                ])
                ->sortable(),

            Text::make('Name')
                ->sortable(),

            Text::make('Email')
                ->sortable(),

            Text::make('Status', function () {
                return 'ARCHIVE';
            }),

            Date::make('Verified At')
                ->sortable()
                ->nullable(),

            Text::make('Date Archived', function () {
                return $this->updated_at?->toDateString() ?? 'N/A';
            }),
        ];
    }

    public function cards(Request $request)
    {
        return [];
    }

    public function filters(Request $request)
    {
        return [];
    }

    public function lenses(Request $request)
    {
        return [];
    }

    public function actions(Request $request)
    {
        return [];
    }
}