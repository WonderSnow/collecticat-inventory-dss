<?php

namespace App\Providers;

use Laravel\Nova\Nova;
use App\Nova\Metrics\Users;
use App\Nova\Metrics\Quotas;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Cards\Help;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use App\Nova\Metrics\Products;
use Laravel\Nova\Fields\Image;
use App\Nova\Metrics\OrdersTrend;
use Spatie\BackupTool\BackupTool;
use App\Nova\Metrics\PendingOrders;
use Illuminate\Support\Facades\Gate;
use Runline\ProfileTool\ProfileTool;
use App\Nova\Metrics\ConfirmedOrders;
use App\Nova\Metrics\ProductCategories;
use OptimistDigital\NovaSettings\NovaSettings;
use Laravel\Nova\NovaApplicationServiceProvider;
use Signifly\Nova\Cards\ProgressBar\ProgressBar;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Nova::resources([
            \App\Nova\Product::class,
            \App\Nova\Order::class,
            \App\Nova\InventoryAudit::class,
            \App\Nova\User::class,
            \App\Nova\ArchivedUser::class,
            \App\Nova\VerificationCode::class,
        ]);
        
        parent::boot();

        Nova::name('Collecticat');

        Nova::style('custom-nova-style', asset('css/nova-custom.css'));
        
        // Completely remove the Save settings button from the DOM for non-admin users (Employees)
        if (auth()->check() && auth()->user()->role !== \App\Models\User::ROLE_ADMIN) {
            Nova::script('remove-employee-save-btn', '
                const observer = new MutationObserver((mutations, obs) => {
                    document.querySelectorAll("button").forEach(btn => {
                        if (btn.textContent.includes("Save settings") || btn.textContent.includes("Save")) {
                            const wrapper = btn.closest("div.flex.justify-end") || btn.parentElement;
                            if (wrapper) {
                                wrapper.remove();
                            } else {
                                btn.remove();
                            }
                        }
                    });
                });
                observer.observe(document.body, { childList: true, subtree: true });
            ');
        }

        // Combined Settings & User Profile Fields
        NovaSettings::addSettingsFields([
            // Logo URL: Visible and editable ONLY by Administrators
            Text::make('Logo URL', 'logo')
                ->canSee(function ($request) {
                    return $request->user() && $request->user()->role === \App\Models\User::ROLE_ADMIN;
                }),

            // Editable Gender Dropdown for both Admins and Employees
            Select::make('Gender', 'gender')
                ->options([
                    'Male' => 'Male',
                    'Female' => 'Female'
                ])
                ->displayUsingLabels(),

            // Ineditable user info fields without using readonly
            Text::make('Role', 'role_display')
                ->resolveUsing(function () {
                    $user = auth()->user();
                    return ($user?->role === \App\Models\User::ROLE_ADMIN) ? 'Administrator' : 'Employee';
                })
                ->withMeta(['extraAttributes' => ['class' => 'pointer-events-none select-none', 'tabindex' => '-1']])
                ->fillUsing(fn () => null),

            Text::make('Username', 'username_display')
                ->resolveUsing(function () {
                    return auth()->user()?->name ?? 'N/A';
                })
                ->withMeta(['extraAttributes' => ['class' => 'pointer-events-none select-none', 'tabindex' => '-1']])
                ->fillUsing(fn () => null),

            Text::make('Email', 'email_display')
                ->resolveUsing(function () {
                    return auth()->user()?->email ?? 'N/A';
                })
                ->withMeta(['extraAttributes' => ['class' => 'pointer-events-none select-none', 'tabindex' => '-1']])
                ->fillUsing(fn () => null),
        ]);
    }

    /**
     * Register the Nova routes.
     *
     * @return void
     */
    protected function routes()
    {
        Nova::routes()
                ->withAuthenticationRoutes()
                ->withPasswordResetRoutes()
                ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewNova', function ($user) {
            if ($user->status === 'ARCHIVE') {
                return false;
            }

            return true;
        });
    }

    /**
     * Get the cards that should be displayed on the default Nova dashboard.
     *
     * @return array
     */
    protected function cards()
    {
        $orderToday = \App\Models\Order::whereStatus(\App\Models\Order::STATUS_CONFIRMED)
            ->whereEmployeeId(auth()->user()->id)
            ->whereDate('created_at', now())
            ->count();

        $quota = auth()->user()->quota ?? 0;

        $quota_percentage = 0;
        if ($quota > 0) {
            $quota_percentage = $orderToday / $quota;
        }

        $cards = [
            // Time card
            (new \Richardkeep\NovaTimenow\NovaTimenow)->timezones([
                'Africa/Nairobi',
                'America/Mexico_City',
                'Australia/Sydney',
                'Europe/Paris',
                'Asia/Manila',
                'Asia/Tokyo',
            ])->defaultTimezone('Asia/Manila')
            ->canSee(function () {
                return config('novax.time_enabled');
            })->width('1/3'),

            // Products Transferred card
            Products::make()
                ->withMeta(['name' => 'Products Transferred into the System'])
                ->width('1/3'),

            // Confirmed Orders card
            ConfirmedOrders::make()
                ->width('1/3')
                ->canSee(function ($request) {
                    return auth()->user()->role === \App\Models\User::ROLE_ADMIN;
                }),
        ];

        // Only add the Daily Quota progress bar if the user is NOT admin@yopmail.com
        if (auth()->user()?->email !== 'admin@yopmail.com') {
            $cards[] = (new ProgressBar)->options([
                'title' => "Daily Quota: {$quota} | Sold: {$orderToday}",
                'percentage' => $quota_percentage,
            ])->width('1/3');
        }

        return $cards;
    }

    /**
     * Get the extra dashboards that should be displayed on the Nova dashboard.
     *
     * @return array
     */
    protected function dashboards()
    {
        return [];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array
     */
    public function tools()
    {
        return [
            // Allow both admins and employees to access profile/settings tools
            (new ProfileTool)->canSee(fn () => config('novax.profile_enabled', true)),
            (new BackupTool)->canSee(fn () => config('novax.back_up_enabled') && auth()->user()->role === \App\Models\User::ROLE_ADMIN),
            (new NovaSettings), // Enabled for all users so they can see their private information under settings
        ];
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            \Laravel\Nova\Http\Controllers\ForgotPasswordController::class, 
            \App\Http\Controllers\ForgotPasswordController::class
        );
    }
}