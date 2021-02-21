<?php

namespace App\Providers;

use App\View\Components\SubscriptionBilling;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Stripe\Stripe;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Livewire::component('subscription-billing', SubscriptionBilling::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        Stripe::setApiKey(config('stripe.secret_key'));
    }
}
