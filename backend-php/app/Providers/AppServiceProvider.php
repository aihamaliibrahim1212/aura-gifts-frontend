<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Force Laravel to use the exact APP_URL — prevents port mismatch
        // issues with OAuth (Google Identity Services checks the origin strictly)
        if (app()->environment('local')) {
            URL::forceRootUrl(config('app.url'));
        }
    }
}
