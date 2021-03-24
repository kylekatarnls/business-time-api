<?php

namespace Tests;

use App\Models\User;
use Carbon\Bespin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Cashier\Subscription;
use Stripe\PaymentMethod;
use Stripe\StripeClient;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected ?StripeClient $stripeClient = null;

    protected function setUp(): void
    {
        Bespin::up($this);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Bespin::down();
    }

    protected function removeUserByEmail(string $email): void
    {
        User::where(['email' => $email])->forceDelete();
    }

    protected function newUser(string $name, string $email, array $properties = []): User
    {
        $this->removeUserByEmail($email);

        return User::create(array_merge([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make('G0¤d5tr@ñ9P##55wo&d'),
        ], $properties));
    }

    protected function newZiggy(): User
    {
        return $this->newUser('David Bowie', 'ziggy@star.dust');
    }

    protected function reloadUser(User $user): User
    {
        return User::find($user->id);
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

    protected function getPaymentMethod(string $number = '4242424242424242'): PaymentMethod
    {
        $cardExpiration = now()->addMonths(2);

        return $this->getStripeClient()->paymentMethods->create([
            'type' => 'card',
            'card' => [
                'number' => $number,
                'exp_month' => $cardExpiration->month,
                'exp_year' => $cardExpiration->year,
                'cvc' => '314',
            ],
        ]);
    }

    public function subscribePlan(
        User $user,
        string $planId,
        string $recurrence,
        ?string $paymentMethod = null
    ): Subscription {
        if ($paymentMethod === null) {
            $user->updateDefaultPaymentMethod($this->getPaymentMethod());
        }

        $user->cancelSubscriptionsSilently();

        return $user->subscribePlan($planId, $recurrence, $paymentMethod);
    }

    public function assertResponseStatus(TestResponse $response, $status): TestResponse
    {
        $actual = $response->getStatusCode();

        $this->assertSame(
            $actual, $status,
            "Expected status code {$status} but received {$actual}.\nBody:\n" . $response->getContent(),
        );

        return $response;
    }
}
