<?php

namespace App\Http\Controllers;

use Stripe\Stripe;

final class ProductController extends AbstractController
{
    public function webhooks()
    {
        Stripe::setApiKey(config('stripe.secret_key'));

        var_dump($this->stripe);
        exit;
    }
}
