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

            Number::make('Quota')
                ->rules([
                    Rule::requiredIf(fn () => $request->input('role') === \App\Models\User::ROLE_EMPLOYEE),
                    'nullable',
                    'integer',
                    'max:100',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->input('role') === \App\Models\User::ROLE_EMPLOYEE && (int)$value < 1) {
                            $fail('The quota must be at least 1 when the role is Employee.');
                        }
                    },
                ])
                ->sortable()
                ->canSee(fn () => !$isArchived),

            Text::make('Daily Progress', function () {
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

            // 👇 Add this field to show who created the account
            Text::make('Created By', function () {
                if ($this->email === 'admin@yopmail.com') {
                    return '—';
                }

                // If you are tracking the creator via a column or relationship on your users table:
                // (e.g., if you have a `created_by` foreign key column pointing to the admin's ID)
                if (isset($this->creator) && $this->creator) {
                    return $this->creator->name;
                }

                return 'Inventory Administrator'; // Fallback name
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