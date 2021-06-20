<?php

namespace App\Http\Controllers;

use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Stripe;

final class ProductController extends AbstractController
{
    public function webhooks()
    {
        $payload = @file_get_contents('php://input');
        file_put_contents(
            __DIR__ . '/../../../storage/stripe/hook-' . now()->format('Y-m-d--H-i-s--u') . '.json',
            json_encode(json_decode($payload, true), JSON_PRETTY_PRINT),
        );

        // @codeCoverageIgnoreStart
        return;

        Stripe::setApiKey(config('stripe.secret_key'));
        $event = Event::constructFrom(json_decode($payload, true));

        switch ($event->type) {
            case 'payment_intent.succeeded':
                /** @var PaymentIntent $paymentIntent */
                $paymentIntent = $event->data->object;
                break;
            case 'payment_method.attached':
                /** @var PaymentMethod $paymentMethod */
                $paymentMethod = $event->data->object;
                break;
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        // @codeCoverageIgnoreEnd
    }
}
