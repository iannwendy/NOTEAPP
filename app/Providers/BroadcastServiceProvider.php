<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load broadcast routes file
        Broadcast::routes(['middleware' => ['web', 'auth']]);

        // Uncomment this if you're using channel classes
        require base_path('routes/channels.php');
    }
} 