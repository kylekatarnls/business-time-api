<?php

namespace Tests;

use App\Models\User;
use Carbon\Bespin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Stripe\StripeClient;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected ?StripeClient $stripeClient = null;

    protected function newUser(string $name, string $email, array $properties = []): User
    {
        User::where(['email' => $email])->forceDelete();

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
}
