<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Http\Requests\NovaRequest;

class User extends Resource
{
    public static $group = "";
    public static function availableForNavigation(Request $request)
    {
        return auth()->user()->role === \App\Models\User::ROLE_ADMIN;
    }

    public static $model = \App\Models\User::class;
    public static $title = 'name';

    public static $search = [
        'id', 'name', 'email',
    ];

    public function authorizedToDelete(Request $request)
    {
        return false;
    }

    public function authorizedToUpdate(Request $request)
    {
        if (auth()->user()->role !== \App\Models\User::ROLE_ADMIN) {
            return false;
        }

        if ($this->resource->exists && isset($this->resource->status) && $this->resource->status === 'ARCHIVE') {
            return false;
        }

        return true;
    }

    public function fields(Request $request)
    {
        $isArchived = isset($this->status) && $this->status === 'ARCHIVE';

        return [
            Select::make('Role')
                ->options([
                    \App\Models\User::ROLE_ADMIN => 'Administrator',
                    \App\Models\User::ROLE_EMPLOYEE => 'Employee',
                ])
                ->rules('required')
                ->sortable()
                ->canSee(function ($request) use ($isArchived) {
                    if ($isArchived && $request->route('resourceId')) {
                        return false;
                    }
                    return true;
                }),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            Password::make('Password')
                ->onlyOnForms()
                ->creationRules('required', 'string', 'min:8')
                ->updateRules('nullable', 'string', 'min:8'),

            Select::make('Status', 'status')
                ->options([
                    'ACTIVE' => 'ACTIVE',
                    'ARCHIVE' => 'ARCHIVE',
                ])
                ->displayUsing(fn ($value) => $value ?? 'ACTIVE')
                ->resolveUsing(fn ($value) => $value ?? 'ACTIVE')
                ->rules('required')
                ->sortable()
                ->canSee(function ($request) use ($isArchived) {
                    if ($isArchived && $request->route('resourceId')) {
                        return false;
                    }
                    return true;
                }),

            Number::make('Quota')
                ->rules('required', 'integer', 'min:0')
                ->sortable()
                ->canSee(fn () => !$isArchived),

            Text::make('Daily Progress', function () {
                $progress = $this->orders()->whereStatus(\App\Models\Order::STATUS_CONFIRMED)->whereDate('created_at', now())->count();
                return "<progress value='$progress' max='$this->quota' />";
            })
            ->asHtml()
            ->canSee(fn () => !$isArchived),

            Date::make('Verified At')
                ->sortable()
                ->nullable()
                ->hideWhenCreating()
                ->hideWhenUpdating(),

            Text::make('Date Archived', function () {
                return $this->status === 'ARCHIVE' ? ($this->updated_at?->toDateString() ?? '-') : '-';
            })
            ->sortable()
            ->hideFromIndex(),

            HasMany::make('Orders', 'orders', Order::class)
                ->canSee(fn () => !$isArchived),
        ];
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        // Hide archived users from the main Users table
        return $query->where('status', '!=', 'ARCHIVE')->orWhereNull('status');
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