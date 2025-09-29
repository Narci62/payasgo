<?php

namespace App\Providers;

use App\Models\Device;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use App\Observers\DeviceObserver;
use App\Services\FcmNotificationService;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Messaging;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // $this->app->bind(ClientInterface::class, function ($app) {
        //     return new Client();
        // });

        // $this->app->singleton(FcmNotificationService::class, function ($app) {
        //     $messaging = new Messaging();
        //     return new FcmNotificationService($messaging);
        // });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Device::observe(DeviceObserver::class);
    }
}
