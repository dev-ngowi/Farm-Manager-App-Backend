<?php

namespace App\Providers;

use App\Models\Menu;
use App\Models\User;
use App\Models\SettingApp;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Observers\GlobalActivityLogger;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Only register Telescope & IDE Helper in local env AND if packages exist
        if ($this->app->environment('local')) {
            // Safe Telescope registration
            if (class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
                $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
                $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            }

            // Safe IDE Helper (barryvdh/laravel-ide-helper)
            if (class_exists(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class)) {
                $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Register observers
        User::observe(GlobalActivityLogger::class);
        Role::observe(GlobalActivityLogger::class);
        Permission::observe(GlobalActivityLogger::class);
        Menu::observe(GlobalActivityLogger::class);
        SettingApp::observe(GlobalActivityLogger::class);

        // Share global view data (cached + TZ ready)
        View::composer('*', function ($view) {
            $settings = cache()->remember('app_settings', now()->addHours(24), fn() =>
                SettingApp::first() ?? new SettingApp()
            );

            $view->with([
                'settings'     => $settings,
                'authUser'     => auth()->user()?->load('roles'),
                'appName'      => $settings->app_name ?? config('app.name'),
                'currentTime'  => now('Africa/Dar_es_Salaam')->format('d M Y, h:i A'),
                'currency'     => $settings->currency_symbol ?? 'TZS',
                'dateFormat'   => $settings->date_format ?? 'd/m/Y',
            ]);
        });
    }
}
