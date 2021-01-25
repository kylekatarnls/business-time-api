<?php

namespace Tests\Unit\Model;

use Tests\TestCase;

final class RefundTest extends TestCase
{
    public function testRefund(): void
    {
        $ziggy = $this->newZiggy();
        $customer = $ziggy->createAsStripeCustomer();
        $cardExpiration = now()->addMonths(2);
        $stripe = $this->getStripeClient();
        $paymentMethod = $stripe->paymentMethods->create([
            'type' => 'card',
            'card' => [
                'number' => '4242424242424242',
                'exp_month' => $cardExpiration->month,
                'exp_year' => $cardExpiration->year,
                'cvc' => '314',
            ],
        ]);
        $ziggy->updateDefaultPaymentMethod($paymentMethod);
//        $paymentIntent = $stripe->paymentIntents->create([
//            'amount' => 34900,
//            'currency' => 'eur',
//            'payment_method_types' => ['card'],
//            'setup_future_usage' => 'off_session',
//        ]);
        $ziggy->cancelSubscriptionsSilently();
        $subscription = $ziggy->subscribePlan('premium', 'yearly');
        $this->assertSame(
            config('plan.premium.id'),
            $ziggy->getActiveSubscription()->items->data[0]->plan['product'],
        );

        return;

        // TODO finish test
        foreach ($ziggy->getSubscriptions() as $subscription) {
            var_dump(
                $subscription->active(),
                $subscription->latestPayment()
            );
        }
        exit;
        $ziggy->refundUntil(150);

        $amounts = [];

        foreach ($ziggy->refunds as $refund) {
            $amounts[] = $refund->getAmount();
        }

        $this->assertTrue($ziggy->subscribed('premium'));
        $this->assertSame([1.5], $amounts);
    }
}
