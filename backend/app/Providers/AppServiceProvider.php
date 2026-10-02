<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        foreach ([\App\Models\Card::class, \App\Models\Benefit::class, \App\Models\Statement::class, \App\Models\MonthlySpendEntry::class] as $model) {
            $model::observe(\App\Observers\RevisionObserver::class);
        }
    }
}
