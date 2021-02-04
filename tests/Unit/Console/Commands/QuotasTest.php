<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\Quotas;
use App\Mail\LimitThreshold;
use App\Models\ApiAuthorization;
use Carbon\Carbonite\Attribute\Freeze;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

final class QuotasTest extends TestCase
{
    public function testQuotasCommandFreePlan(): void
    {
        Mail::fake();

        $ana = $this->newUser('Ana', 'ana@selfbuild.fr');
        $bob = $this->newUser('Bob', 'bob@selfbuild.fr');

        $this->assertFalse($ana->canSubscribeAPlan());
        $this->assertFalse($ana->hasVerifiedProperties());
        /** @var ApiAuthorization $auth */
        $auth = $ana->apiAuthorizations()->create([
            'name'  => 'Ana Website',
            'type'  => 'domain',
            'value' => 'ana.github.io',
        ]);
        $ana = $this->reloadUser($ana);
        $this->assertFalse($ana->canSubscribeAPlan());
        $this->assertFalse($ana->hasVerifiedProperties());
        $auth->verify();
        $ana = $this->reloadUser($ana);
        $this->assertTrue($ana->hasVerifiedProperties());
        $this->assertTrue($ana->canSubscribeAPlan());

        $this->instance(Quotas::class, new Quotas(
            [80, 100],
            [90, 100],
            static fn () => [$ana, $bob],
        ));

        $getCountFile = new ReflectionMethod(ApiAuthorization::class, 'getCountFile');
        $getCountFile->setAccessible(true);
        $countFile = $getCountFile->invoke($auth);

        file_put_contents($countFile, '3999');

        $this
            ->artisan('quotas', ['--verbose' => true, '--dry-run' => true])
            ->expectsOutput('free: Ana')
            ->expectsOutput($ana->id . '   ' . $ana->email)
            ->doesntExpectOutput(' - ana.github.io                          3 999 / 5 000   80%  >=  80%')
            ->doesntExpectOutput('free: Bob')
            ->assertExitCode(0);

        Mail::assertNothingSent();

        file_put_contents($countFile, '4100');

        $this
            ->artisan('quotas', ['--verbose' => true, '--dry-run' => true])
            ->expectsOutput('free: Ana')
            ->expectsOutput($ana->id . '   ' . $ana->email)
            ->expectsOutput(' - ana.github.io                          4 100 / 5 000   82%  >=  80%')
            ->doesntExpectOutput('free: Bob')
            ->assertExitCode(0);

        Mail::assertNothingSent();

        Log::shouldReceive('info')->with(
            "Notice to ana@selfbuild.fr\n" .
            "Le quota pour ana.github.io a dépassé 80%\n" .
            'La propriété ana.github.io va bientôt être suspendue, ' .
            'vous pouvez augmenter la limite mensuelle en utilisant le bouton ci-dessous :',
        );

        $this
            ->artisan('quotas', ['--verbose' => true])
            ->expectsOutput('free: Ana')
            ->expectsOutput($ana->id . '   ' . $ana->email)
            ->expectsOutput(' - ana.github.io                          4 100 / 5 000   82%  >=  80%')
            ->doesntExpectOutput('free: Bob')
            ->assertExitCode(0);

        Mail::assertSent(
            LimitThreshold::class,
            static fn (LimitThreshold $mail) => $mail->hasTo('ana@selfbuild.fr'),
        );
        Mail::assertSent(
            LimitThreshold::class,
            static fn (LimitThreshold $mail) => $mail->hasTo(config('app.super_admin')),
        );

        $this
            ->artisan('quotas', ['--verbose' => true])
            ->expectsOutput('free: Ana')
            ->expectsOutput($ana->id . '   ' . $ana->email)
            ->doesntExpectOutput(' - ana.github.io                          4 100 / 5 000   82%  >=  80%')
            ->doesntExpectOutput('free: Bob')
            ->assertExitCode(0);

        Mail::assertSent(LimitThreshold::class, 2);
    }

    public function testQuotasCommandPaidPlan(): void
    {
        Mail::fake();

        $ana = $this->newUser('Ana', 'ana@selfbuild.fr');
        $bob = $this->newUser('Bob', 'bob@selfbuild.fr');

        $this->assertFalse($ana->canSubscribeAPlan());
        $this->assertFalse($ana->hasVerifiedProperties());
        /** @var ApiAuthorization $auth */
        $auth = $ana->apiAuthorizations()->create([
            'name'  => 'Ana Website',
            'type'  => 'domain',
            'value' => 'ana.github.io',
        ]);
        $ana = $this->reloadUser($ana);
        $this->assertFalse($ana->canSubscribeAPlan());
        $this->assertFalse($ana->hasVerifiedProperties());
        $auth->verify();
        $ana->createAsStripeCustomer();
        $this->subscribePlan($ana, 'pro', 'yearly');
        $ana->last_subscribe_at = now();
        $ana->save();
        $ana = $this->reloadUser($ana);
        $this->assertTrue($ana->hasVerifiedProperties());
        $this->assertTrue($ana->canSubscribeAPlan());

        $this->instance(Quotas::class, new Quotas(
            [80, 100],
            [90, 100],
            static fn() => [$ana, $bob],
        ));

        $getCountFile = new ReflectionMethod(ApiAuthorization::class, 'getCountFile');
        $getCountFile->setAccessible(true);
        $countFile = $getCountFile->invoke($auth);

        file_put_contents($countFile, '3999');

        $this
            ->artisan('quotas', ['--verbose' => true])
            ->expectsOutput('pro: Ana')
            ->expectsOutput($ana->id.'   '.$ana->email)
            ->expectsOutput('0 months           0 / 200 000    0%')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function testQuotasCommandUnlimitedPlan(): void
    {
        Mail::fake();

        $ana = $this->newUser('Ana', 'ana@selfbuild.fr');
        $bob = $this->newUser('Bob', 'bob@selfbuild.fr');

        $this->assertFalse($ana->canSubscribeAPlan());
        $this->assertFalse($ana->hasVerifiedProperties());
        /** @var ApiAuthorization $auth */
        $auth = $ana->apiAuthorizations()->create([
            'name'  => 'Ana Website',
            'type'  => 'domain',
            'value' => 'ana.github.io',
        ]);
        $ana = $this->reloadUser($ana);
        $this->assertFalse($ana->canSubscribeAPlan());
        $this->assertFalse($ana->hasVerifiedProperties());
        $auth->verify();
        $ana->createAsStripeCustomer();
        $this->subscribePlan($ana, 'premium', 'yearly');
        $ana->last_subscribe_at = now();
        $ana->save();
        $ana = $this->reloadUser($ana);
        $this->assertTrue($ana->hasVerifiedProperties());
        $this->assertTrue($ana->canSubscribeAPlan());

        $this->instance(Quotas::class, new Quotas(
            [80, 100],
            [90, 100],
            static fn() => [$ana, $bob],
        ));

        $getCountFile = new ReflectionMethod(ApiAuthorization::class, 'getCountFile');
        $getCountFile->setAccessible(true);
        $countFile = $getCountFile->invoke($auth);

        file_put_contents($countFile, '3999');

        $this
            ->artisan('quotas', ['--verbose' => true])
            ->expectsOutput('premium: Ana')
            ->expectsOutput($ana->id.'   '.$ana->email)
            ->doesntExpectOutput(' - ana.github.io                          3 999 / 5 000   80%  >=  80%')
            ->doesntExpectOutput('free: Bob')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }

    #[Freeze('2021-01-31 23:58:12 UTC')]
    public function testEndOfMonth(): void
    {
        $command = new Quotas();
        $now = new ReflectionProperty(Quotas::class, 'now');
        $now->setAccessible(true);
        $year = new ReflectionProperty(Quotas::class, 'year');
        $year->setAccessible(true);
        $month = new ReflectionProperty(Quotas::class, 'month');
        $month->setAccessible(true);

        $this->assertSame(2021, $year->getValue($command));
        $this->assertSame(2, $month->getValue($command));
        $this->assertSame('2021-02', $now->getValue($command)->format('Y-m'));

        $apiAuthorizationThresholds = new ReflectionProperty(Quotas::class, 'apiAuthorizationThresholds');
        $apiAuthorizationThresholds->setAccessible(true);
        $subscriptionThreshold = new ReflectionProperty(Quotas::class, 'subscriptionThreshold');
        $subscriptionThreshold->setAccessible(true);

        $this->assertSame(
            config('notification.quota.api_authorization'),
            $apiAuthorizationThresholds->getValue($command),
        );
        $this->assertSame(
            config('notification.quota.subscription'),
            $subscriptionThreshold->getValue($command),
        );
    }
}
