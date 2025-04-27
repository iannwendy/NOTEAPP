<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Config;

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
        // Force HTTPS in production
        if (config('app.env') === 'production' || config('secure.force_https', true)) {
            URL::forceScheme('https');
            
            // Make sure all generated URLs use HTTPS
            if ($this->app->environment('production')) {
                $this->app['request']->server->set('HTTPS', 'on');
            }
            
            // If the request is through a proxy and has the HTTP_X_FORWARDED_PROTO header set to https, set the HTTPS server var
            if (Request::header('X-Forwarded-Proto') == 'https') {
                $this->app['request']->server->set('HTTPS', 'on');
            }
        }
        
        // Set schema string length if running on older MySQL/MariaDB
        Schema::defaultStringLength(191);
    }
}
