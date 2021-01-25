<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stripe\StripeClient;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected ?StripeClient $stripeClient = null;

    protected function newZiggy(): User
    {
        User::where(['email' => 'ziggy@star.dust'])->forceDelete();

        return User::create([
            'name' => 'David Bowie',
            'email' => 'ziggy@star.dust',
            'password' => 'G0¤d5tr@ñ9P##55wo&d',
        ]);
    }

    protected function getStripeClient(): StripeClient
    {
        if ($this->stripeClient === null) {
            $this->stripeClient = new StripeClient([
                'api_key' => config('stripe.secret_key'),
            ]);
        }

        return $this->stripeClient;
    }
}
