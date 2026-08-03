<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));
        Carbon::setLocale('id');

        // Grant all permissions to 'admin' role automatically
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        \Inertia\Inertia::share([
            'company' => fn() => [
                'id' => session('company_id'),
                'name' => session('company_name'),
                'logo' => session('company_logo'),
            ],
        ]);
    }
}
