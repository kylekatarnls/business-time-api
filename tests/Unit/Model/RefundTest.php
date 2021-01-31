<?php

namespace Tests\Unit\Model;

use App\Models\User;
use Tests\TestCase;

final class RefundTest extends TestCase
{
    public function testRefund(): void
    {
        $ziggy = $this->newZiggy();
        $ziggy->createAsStripeCustomer();
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
        $ziggy->cancelSubscriptionsSilently();
        $subscription = $ziggy->subscribePlan('premium', 'yearly');
        $this->assertSame(
            config('plan.premium.id'),
            $subscription->asStripeSubscription()->items->data[0]->plan['product'],
        );
        $this->assertSame(
            config('plan.premium.id'),
            $ziggy->getActiveSubscription()->items->data[0]->plan['product'],
        );

        /**
         * Reload user.
         *
         * @var User $ziggy
         */
        $ziggy = User::find($ziggy->id);

        $ziggy->refundUntil(1.50);

        $amounts = [];

        $encode = static fn (string $code, int $prefixLength) => substr($code, 0, $prefixLength) .
            round((strlen($code) - $prefixLength) / 6);

        foreach ($ziggy->refunds as $refund) {
            $amounts[] = [
                'user' => $refund->user->name,
                'check' => $refund->asStripeRefund()['id'] === $refund->stripe_refund_id,
                'amount' => $refund->getAmount(),
                'cents_amount' => $refund->cents_amount,
                'stripe_refund_id' => $encode($refund->stripe_refund_id, 3),
                'payment_intent' => $encode($refund->payment_intent, 3),
                'balance_transaction' => $encode($refund->balance_transaction, 4),
                'charge' => $encode($refund->charge, 3),
                'status' => $refund->status,
                'currency' => $refund->currency,
            ];
        }

        $this->assertSame([
            [
                'user' => 'David Bowie',
                'check' => true,
                'amount' => 1.5,
                'cents_amount' => 150,
                'stripe_refund_id' => 're_4',
                'payment_intent' => 'pi_4',
                'balance_transaction' => 'txn_4',
                'charge' => 'ch_4',
                'status' => 'succeeded',
                'currency' => 'eur',
            ],
        ], $amounts);

        $amounts = [];

        foreach ($ziggy->getRefunds() as $refund) {
            $amounts[] = [
                'user' => $refund->user->name,
                'check' => $refund->asStripeRefund()['id'] === $refund->stripe_refund_id,
                'amount' => $refund->getAmount(),
                'cents_amount' => $refund->cents_amount,
                'stripe_refund_id' => $encode($refund->stripe_refund_id, 3),
                'payment_intent' => $encode($refund->payment_intent, 3),
                'balance_transaction' => $encode($refund->balance_transaction, 4),
                'charge' => $encode($refund->charge, 3),
                'status' => $refund->status,
                'currency' => $refund->currency,
            ];
        }

        $this->assertSame([
            [
                'user' => 'David Bowie',
                'check' => true,
                'amount' => 1.5,
                'cents_amount' => 150,
                'stripe_refund_id' => 're_4',
                'payment_intent' => 'pi_4',
                'balance_transaction' => 'txn_4',
                'charge' => 'ch_4',
                'status' => 'succeeded',
                'currency' => 'eur',
            ],
        ], $amounts);
    }
}
