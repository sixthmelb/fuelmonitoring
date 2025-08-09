<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Observers\FuelStorageObserver;
use App\Observers\FuelTruckObserver;
use App\Observers\FuelTransactionObserver;
use App\Models\FuelStorage;
use App\Models\FuelTruck;
use App\Models\FuelTransaction;

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
            FuelStorage::observe(FuelStorageObserver::class);
            FuelTruck::observe(FuelTruckObserver::class);
            FuelTransaction::observe(FuelTransactionObserver::class);
    }
}
