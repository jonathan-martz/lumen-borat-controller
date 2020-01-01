<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class BoratProvider
 * @package App\Providers
 */
class BoratProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
    }
}
