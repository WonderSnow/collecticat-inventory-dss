<?php

namespace App\Providers;

use Laravel\Nova\Nova;
use App\Nova\Metrics\Users;
use App\Nova\Metrics\Quotas;
use Laravel\Nova\Cards\Help;
use Laravel\Nova\Fields\Text;
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
        
        NovaSettings::addSettingsFields([
            Text::make('Logo URL', 'logo'),
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

        return [
            // Your existing timezones & metrics below (anonymous card removed)
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

            Products::make()->withMeta(['name' => 'Products Transferred into the System'])->width('1/3'),
            ConfirmedOrders::make()->width('1/3'),

            (new ProgressBar)->options([
                'title' => "Daily Quota: {$quota} | Sold: {$orderToday}",
                'percentage' => $quota_percentage,
            ])->width('1/3'),
        ];
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
            (new ProfileTool)->canSee(fn () => config('novax.profile_enabled')),
            (new BackupTool)->canSee(fn () => config('novax.back_up_enabled')),
            (new NovaSettings)->canSee(fn () => config('novax.setting_enabled')),
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