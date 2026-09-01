<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Device;
use App\Models\Financing_plan;
use App\Models\Phone;
use App\Models\User;
use App\Observers\DeviceObserver;
use App\Policies\ClientPolicy;
use App\Policies\DevicePolicy;
use App\Policies\FinancingPlanPolicy;
use App\Policies\PhonePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Device::observe(DeviceObserver::class);

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Device::class, DevicePolicy::class);
        Gate::policy(Financing_plan::class, FinancingPlanPolicy::class);
        Gate::policy(Phone::class, PhonePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
