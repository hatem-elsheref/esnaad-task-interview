<?php

namespace App\Providers;

use App\Models\Ingredient;
use App\Observers\IngredientObserver;
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
        Ingredient::observe(IngredientObserver::class);
    }
}
