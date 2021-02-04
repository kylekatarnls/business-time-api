<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\Quotas;
use App\Mail\LimitThreshold;
use App\Models\ApiAuthorization;
use Carbon\CarbonImmutable;
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

        file_put_contents($countFile, '5000');

        Log::shouldReceive('info')->with(
            "Notice to ana@selfbuild.fr\n" .
            "[Attention] Le quota pour ana.github.io a dépassé 100%\n" .
            "La propriété ana.github.io est suspendue jusqu'à la fin du mois, " .
            'vous pouvez la débloquer en utilisant le bouton ci-dessous :',
        );

        $this
            ->artisan('quotas', ['--verbose' => true])
            ->expectsOutput('free: Ana')
            ->expectsOutput($ana->id . '   ' . $ana->email)
            ->expectsOutput(' - ana.github.io                          5 000 / 5 000  100%  >=  100%')
            ->doesntExpectOutput('free: Bob')
            ->assertExitCode(0);

        Mail::assertSent(
            LimitThreshold::class,
            static fn(LimitThreshold $mail) => $mail->hasTo('ana@selfbuild.fr'),
        );
        Mail::assertSent(
            LimitThreshold::class,
            static fn(LimitThreshold $mail) => $mail->hasTo(config('app.super_admin')),
        );

        Mail::assertSent(LimitThreshold::class, 4);
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
        $subscription = $this->subscribePlan($ana, 'pro', 'yearly');
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
            ->expectsOutput($ana->id . '   ' . $ana->email)
            ->expectsOutput('0 months           0 / 200 000    0%')
            ->assertExitCode(0);

        $this
            ->artisan('quotas')
            ->doesntExpectOutput('pro: Ana')
            ->doesntExpectOutput($ana->id . '   ' . $ana->email)
            ->doesntExpectOutput('0 months           0 / 200 000    0%')
            ->assertExitCode(0);

        Mail::assertNothingSent();

        $subscriptionBaseDirectory = __DIR__.'/../../../../data/subscription-count/';
        $subscriptionDirectory = $subscriptionBaseDirectory . 's' . $subscription->id;
        @mkdir($subscriptionDirectory, 0777, true);
        $subscriptionFile = $subscriptionDirectory . '/m0.txt';

        $end = CarbonImmutable::createFromTimestamp($subscription->current_period_end)->isoFormat('LLLL');
        file_put_contents($subscriptionFile, '188754');
        $ana = $this->reloadUser($ana);

        Log::shouldReceive('info')->with(
            "Notice to ana@selfbuild.fr\n" .
            "Le quota pour Vicopo Pro a dépassé 80%\n" .
            'Votre abonnement Vicopo Pro a atteint 80%, ' .
            "si vous pensez qu'il peut atteindre 100% avant $end, vous pouvez augmenter la limite en cliquant sur le bouton ci-dessous :",
        );

        $this
            ->artisan('quotas', ['--verbose' => true])
            ->expectsOutput('pro: Ana')
            ->expectsOutput($ana->id . '   ' . $ana->email)
            ->expectsOutput('0 months     188 754 / 200 000   94%')
            ->expectsOutput(' - Pro                                 188 754 / 200 000   94%  >=  80%')
            ->assertExitCode(0);

        Mail::assertSent(
            LimitThreshold::class,
            static fn(LimitThreshold $mail) => $mail->hasTo('ana@selfbuild.fr'),
        );
        Mail::assertSent(
            LimitThreshold::class,
            static fn(LimitThreshold $mail) => $mail->hasTo(config('app.super_admin')),
        );

        file_put_contents($subscriptionFile, '1588754');
        $ana = $this->reloadUser($ana);

        Log::shouldReceive('info')->with(
            "Notice to ana@selfbuild.fr\n" .
            "[Attention] Le quota pour Vicopo Pro a dépassé 100%\n" .
            "Votre abonnement Vicopo Pro est désormais suspendu jusqu'à $end, " .
            "vous pouvez le débloquer en utilisant le bouton ci-desous :",
        );

        $this
            ->artisan('quotas', ['--verbose' => true])
            ->expectsOutput('pro: Ana')
            ->expectsOutput($ana->id . '   ' . $ana->email)
            ->expectsOutput('0 months   1 588 754 / 200 000  794%')
            ->expectsOutput(' - Pro                                 1 588 754 / 200 000  794%  >=  100%')
            ->assertExitCode(0);

        Mail::assertSent(
            LimitThreshold::class,
            static fn(LimitThreshold $mail) => $mail->hasTo('ana@selfbuild.fr'),
        );
        Mail::assertSent(
            LimitThreshold::class,
            static fn(LimitThreshold $mail) => $mail->hasTo(config('app.super_admin')),
        );

        Mail::assertSent(LimitThreshold::class, 4);
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
            ->expectsOutput($ana->id . '   ' . $ana->email)
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
