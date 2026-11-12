<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // madock's nginx terminates TLS and forwards the request to PHP over
        // http. Without this, asset() and form actions come out as http://
        // inside an https page -> "Mixed Content", and the browser blocks
        // CSS/JS. Follows APP_URL: if it is https, every generated URL is too.
        if (strpos((string) config('app.url'), 'https://') === 0) {
            \URL::forceScheme('https');
        }
    }
}
