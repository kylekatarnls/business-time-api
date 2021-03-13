<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiAuthorization;
use App\Models\Plan;
use App\Models\User;
use App\View\Components\SubscriptionBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use ReflectionMethod;
use SessionHandler;
use Tests\TestCase;

final class ControllerTest extends TestCase
{
    public function testHome(): void
    {
        $controller = new Controller();
        $home = $controller->home();

        $this->assertInstanceOf(RedirectResponse::class, $home);
        $this->assertTrue($home->isRedirect(route('dashboard')));
    }

    public function testIncreaseLimit(): void
    {
        $controller = new Controller();
        $request = new Request();
        $session = new Store('session', new SessionHandler());
        $request->setLaravelSession($session);
        $response = $controller->increaseLimit($request, 'abc');

        $this->assertSame('abc', $session->get('increase-limit'));
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(route('dashboard')));
    }

    public function testGetGuestPlan(): void
    {
        $controller = new Controller();
        $getGuestPlan = new ReflectionMethod(Controller::class, 'getGuestPlan');
        $getGuestPlan->setAccessible(true);
        /** @var Plan $plan */
        $plan = $getGuestPlan->invoke($controller);

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertSame([
            'title'    => 'Guest',
            'name'     => 'Vicopo Guest',
            'price'    => 0,
            'limit'    => 1_000,
            'product'  => 'guest',
            'currency' => 'eur',
        ], $plan->getArrayCopy());
    }

    public function testGetFreePlan(): void
    {
        $controller = new Controller();
        $getFreePlan = new ReflectionMethod(Controller::class, 'getFreePlan');
        $getFreePlan->setAccessible(true);
        /** @var Plan $plan */
        $plan = $getFreePlan->invoke($controller);

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertSame([
            'title'    => 'Free',
            'name'     => 'Vicopo Free',
            'price'    => 0,
            'limit'    => 5_000,
            'product'  => 'free',
            'currency' => 'eur',
        ], $plan->getArrayCopy());
    }

    public function testSubscription(): void
    {
        $ziggy = $this->newZiggy();
        $ziggy->createAsStripeCustomer();
        /** @var ApiAuthorization $auth */
        $auth = $ziggy->apiAuthorizations()->create([
            'name'  => 'Website',
            'type'  => 'domain',
            'value' => 'web.github.io',
        ]);
        $auth->verify();
        $ziggy = $this->reloadUser($ziggy);
        $content = $this->getDashboardFor($ziggy)->getContent();

        $this->assertStringNotContainsString(
            'Terminer l&#039;abonnement (annuler le renouvellement à échéance).',
            $content,
        );
        $this->assertStringNotContainsString(
            'Activer le renouvellement automatique',
            $content,
        );

        $this->subscribePlan($ziggy, 'start', 'monthly');
        $ziggy = $this->reloadUser($ziggy);
        Auth::login($ziggy);
        $content = $this->getDashboardFor($ziggy)->getContent();

        $this->assertStringContainsString(
            'Terminer l&#039;abonnement (annuler le renouvellement à échéance).',
            $content,
        );
        $this->assertStringNotContainsString(
            'Activer le renouvellement automatique',
            $content,
        );

        $subscriptionBilling = new SubscriptionBilling();
        $subscriptionBilling->viewData = ['subscription' => $ziggy->getActiveSubscription()->id];
        $ziggy = $this->reloadUser($ziggy);
        Auth::login($ziggy);
        $this->assertNull($ziggy->getActiveSubscription()->cancel_at);
        $this->assertFalse($subscriptionBilling->confirmingSubscriptionCancellation);
        $subscriptionBilling->confirmSubscriptionCancellation();
        $ziggy = $this->reloadUser($ziggy);
        Auth::login($ziggy);
        $this->assertNull($ziggy->getActiveSubscription()->cancel_at);
        $this->assertTrue($subscriptionBilling->confirmingSubscriptionCancellation);
        $subscriptionBilling->cancelSubscription();
        $ziggy = $this->reloadUser($ziggy);
        Auth::login($ziggy);
        $this->assertNotNull($ziggy->getActiveSubscription()->cancel_at);
        $content = $this->getDashboardFor($ziggy)->getContent();

        $this->assertStringContainsString(
            'Activer le renouvellement automatique',
            $content,
        );
        $this->assertStringNotContainsString(
            'Terminer l&#039;abonnement (annuler le renouvellement à échéance).',
            $content,
        );

        $ziggy = $this->reloadUser($ziggy);
        Auth::login($ziggy);
        [$controller, $request] = $this->getControllerFor($ziggy);
        $response = $controller->autorenew($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(route('dashboard')));

        $ziggy = $this->reloadUser($ziggy);
        Auth::login($ziggy);
        $content = $this->getDashboardFor($ziggy)->getContent();

        $this->assertStringContainsString(
            'Terminer l&#039;abonnement (annuler le renouvellement à échéance).',
            $content,
        );
        $this->assertStringNotContainsString(
            'Activer le renouvellement automatique',
            $content,
        );
    }

    public function testPlan(): void
    {
        $ziggy = $this->newZiggy();
        Auth::login($ziggy);
        $this->assertFalse($ziggy->hasStripeId());
        /**
         * @var Controller $controller
         * @var Request $request
         */
        [$controller, $request] = $this->getControllerFor($ziggy);
        $view = $controller->plan($request);
        $this->assertTrue($ziggy->hasStripeId());
        $data = $view->getData();

        $this->assertSame($ziggy, $data['user']);
        $this->assertSame(config('stripe.publishable_key'), $data['stripeKey']);
        $this->assertSame(3, $data['numberOfPlans']);
        $this->assertSame([
            'start' => 'Start',
            'pro' => 'Pro',
            'premium' => 'Premium',
        ], array_map(static fn (array $plan) => $plan['title'], $data['plans']));
        $this->assertSame('plan', $view->name());
    }

    /**
     * @param User $user
     *
     * @return array{Controller, Request, Store}
     */
    private function getControllerFor(User $user): array
    {
        $controller = new Controller();
        $request = new Request();
        $request->setUserResolver(static fn () => $user);
        $session = new Store('session', new SessionHandler());
        $request->setLaravelSession($session);

        return [$controller, $request, $session];
    }

    private function getDashboardFor(User $user): Response
    {
        [$controller, $request] = $this->getControllerFor($user);

        return $controller->dashboard($request);
    }
}
