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
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Card;
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
        'name', 'email', 'status', 'role'
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
        if ($this->email === 'admin@yopmail.com') {
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

            Text::make('Username', 'name')
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
                ->updateRules('nullable', 'string', 'min:8')
                ->hideWhenUpdating(),

            Select::make('Status', 'status')
                ->options([
                    'ACTIVE' => 'ACTIVE',
                    'DISACTIVATE' => 'DISACTIVATE',
                    'ARCHIVE' => 'ARCHIVE',
                ])
                ->displayUsing(function ($value) {
                    if ($value === 'DISACTIVATE') {
                        return 'DISACTIVATED';
                    }
                    return $value ?? 'ACTIVE';
                })
                ->resolveUsing(fn ($value) => $value ?? 'ACTIVE')
                ->default('ACTIVE')
                ->hideWhenCreating()
                ->rules('required', 'in:ACTIVE,DISACTIVATE,ARCHIVE')
                ->sortable()
                ->canSee(function ($request) use ($isArchived) {
                    if ($isArchived && $request->route('resourceId')) {
                        return false;
                    }
                    return true;
                }),

            // 👇 Changed to Number field mapped to 'quota' column so admins can edit it
            // Quota: Editable for all users except admin@yopmail.com
            Number::make('Quota', 'quota')
                ->sortable()
                ->rules('nullable', 'integer', 'min:0')
                ->canSee(fn () => !$isArchived)
                ->displayUsing(function ($value) {
                    return $this->email === 'admin@yopmail.com' ? '—' : $value;
                })
                ->withMeta([
                    'readonly' => $this->email === 'admin@yopmail.com',
                ]),

            Text::make('Daily Progress', function () {
                if ($this->email === 'admin@yopmail.com') {
                    return '—';
                }

                $progress = $this->orders()->whereStatus(\App\Models\Order::STATUS_CONFIRMED)->whereDate('created_at', now())->count();
                $quota = $this->quota ?? 0;
                
                return "
                    <div style='display: flex; align-items: center; gap: 8px;'>
                        <progress value='{$progress}' max='{$quota}' style='width: 100px;'></progress>
                        <span style='font-size: 13px; font-weight: 600;'>{$progress} / {$quota}</span>
                    </div>
                ";
            })
            ->asHtml()
            ->canSee(fn () => !$isArchived),

            Date::make('Verified At')
                ->sortable()
                ->nullable()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->hideFromIndex(),
            
            DateTime::make('Created At', 'created_at')
                ->sortable()
                ->exceptOnForms()
                ->hideFromIndex(),

            Text::make('Created By', function () {
                if ($this->email === 'admin@yopmail.com') {
                    return '—';
                }

                if (isset($this->creator) && $this->creator) {
                    return $this->creator->name;
                }

                return 'Inventory Administrator';
            })->hideFromIndex(),

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
        return $query->where(function ($q) {
            $q->where('status', '!=', 'ARCHIVE')
              ->orWhereNull('status');
        });
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