<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\Contact;
use App\Models\ApiAuthorization;
use App\Models\Plan;
use App\Models\User;
use App\View\Components\SubscriptionBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\Mailer;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\View\Factory;
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

    public function testContact(): void
    {
        $controller = new Controller();
        $contact = $controller->contact($this->getRequest())->render();

        $this->assertMatchesRegularExpression('`<h2[^>]+>\s*Contact\s*</h2>`', $contact);
        $this->assertDoesNotMatchRegularExpression('`<h2[^>]+>\s*Exonération\s*</h2>`', $contact);
        $this->assertStringNotContainsString('Message envoyé.', $contact);

        [$request, $session] = $this->getRequestWithSession();
        $session->put('sent', true);
        $contact = $controller->contact($request)->render();

        $this->assertMatchesRegularExpression('`<h2[^>]+>\s*Contact\s*</h2>`', $contact);
        $this->assertDoesNotMatchRegularExpression('`<h2[^>]+>\s*Exonération\s*</h2>`', $contact);
        $this->assertStringContainsString('Message envoyé.', $contact);

        $exonerate = $controller->exonerate($this->getRequest());

        $this->assertInstanceOf(RedirectResponse::class, $exonerate);
        $this->assertSame([
            "Vous devez d'abord enregistrer votre IP ou domaine puis utiliser la validation pour confirmer que vous en êtes le propriétaire.",
        ], $exonerate->getSession()->get('errors'));
        $this->assertTrue($exonerate->isRedirect(route('dashboard')));

        $ziggy = $this->newZiggy();
        $ziggy->createAsStripeCustomer();
        /** @var ApiAuthorization $auth */
        $auth = $ziggy->apiAuthorizations()->create([
            'name'  => 'Website',
            'type'  => 'domain',
            'value' => 'web.github.io',
        ]);
        $auth->verify();
        Auth::login($ziggy);
        [$controller, $request] = $this->getControllerFor($ziggy);
        $exonerate = $controller->exonerate($request)->render();

        $this->assertMatchesRegularExpression('`<h2[^>]+>\s*Exonération\s*</h2>`', $exonerate);
        $this->assertDoesNotMatchRegularExpression('`<h2[^>]+>\s*Contact\s*</h2>`', $exonerate);

    }

    public function getPostContactTemplate(): iterable
    {
        yield ['contact', 'Confirmation de message', null];
        yield ['exonerate', "Demande d'exonération soumise", 'exonerate'];
    }

    /**
     * @dataProvider getPostContactTemplate
     */
    public function testPostContact(string $expectedRoute, string $expectedSubject, ?string $template): void
    {
        /** @var Factory $viewFactory */
        $viewFactory = View::getFacadeRoot();
        Mail::fake();

        $controller = new Controller();
        $fields = [
            'email' => 'my@email',
            'message' => "My <strong>message</strong>\non multiple lines.",
        ];

        if ($template) {
            $fields['template'] = $template;
        }

        $request = $this->getRequest([], $fields);

        Mail::assertNothingSent();

        $contact = $controller->postContact($request);
        $this->assertInstanceOf(RedirectResponse::class, $contact);
        $this->assertTrue($contact->getSession()->get('sent'));
        $this->assertTrue($contact->isRedirect(route($expectedRoute)));

        $getRender = static fn (Contact $mail) => $viewFactory->make($mail->build()->view, $mail->viewData)->render();
        Mail::assertSent(
            Contact::class,
            static fn (Contact $mail) => ($render = $getRender($mail)) &&
                $mail->hasTo('my@email') &&
                $mail->subject === $expectedSubject &&
                $mail->viewData['content'] === "My <strong>message</strong>\non multiple lines." &&
                !preg_match('`my@email`', $render) &&
                preg_match(
                    '`Merci pour votre message, nous reviendrons rapidement vers vous\.'.
                    '<br\s?/?><br\s?/?>\s*Vicopo[\s\S]+'.
                    'My &lt;strong&gt;message&lt;/strong&gt;<br\s?/?>\non multiple lines\.`',
                    $render),
        );
        Mail::assertSent(
            Contact::class,
            static fn (Contact $mail) => ($render = $getRender($mail)) &&
                $mail->hasTo('my@email') &&
                $mail->subject === $expectedSubject &&
                $mail->viewData['content'] === "My <strong>message</strong>\non multiple lines." &&
                !preg_match('`my@email`', $render) &&
                preg_match(
                    '`Merci pour votre message, nous reviendrons rapidement vers vous\.' .
                    '<br\s?/?><br\s?/?>\s*Vicopo[\s\S]+' .
                    'My &lt;strong&gt;message&lt;/strong&gt;<br\s?/?>\non multiple lines\.`',
                    $render),
        );
        Mail::assertSent(
            Contact::class,
            static fn (Contact $mail) => ($render = $getRender($mail)) &&
                $mail->hasTo(config('app.super_admin')) &&
                $mail->subject === $expectedSubject &&
                $mail->viewData['content'] === "my@email\n\nMy <strong>message</strong>\non multiple lines." &&
                preg_match(
                    '`Merci pour votre message, nous reviendrons rapidement vers vous\.' .
                    '<br\s?/?><br\s?/?>\s*Vicopo[\s\S]+' .
                    'my@email<br\s?/?>\n<br\s?/?>\nMy &lt;strong&gt;message&lt;/strong&gt;<br\s?/?>\non multiple lines\.`',
                    $render),
        );
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
        $this->assertNull($data['closureFees']);
        $this->assertSame([
            'start' => 'Start',
            'pro' => 'Pro',
            'premium' => 'Premium',
        ], array_map(static fn (array $plan) => $plan['title'], $data['plans']));
        $this->assertSame('plan', $view->name());
    }

    public function testRejectIntent(): void
    {
        $controller = new Controller();
        [$request, $session] = $this->getRequestWithSession([
            'intent' => 'abc123',
            'error' => 'Refused payment',
        ]);
        $session->put('intent-data-abc123', [
            'planId' => 'A1',
            'recurrence' => 'monthly',
            'cardChoice' => '42424242',
        ]);
        $response = $controller->rejectIntent($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(route('plan')));
        $this->assertSame([
            'selectedPlan' => 'A1',
            '_flash' => [
                'new' => [
                    0 => 'selectedPlan',
                    1 => 'selectedRecurrence',
                    2 => 'selectedCard',
                    3 => 'paymentError',
                ],
                'old' => [],
            ],
            'selectedRecurrence' => 'monthly',
            'selectedCard' => '42424242',
            'paymentError' => 'Refused payment',
        ], $response->getSession()->all());
    }

    public function testGetPlansCredit(): void
    {
        $getPlansCredit = new ReflectionMethod(Controller::class, 'getPlansCredit');
        $getPlansCredit->setAccessible(true);
        $ziggy = $this->newZiggy();
        $ziggy->createAsStripeCustomer();

        $this->assertSame(0.0, $getPlansCredit->invoke(new Controller(), $ziggy));
    }

    /**
     * @param User $user
     *
     * @return array{Controller, Request, Store}
     */
    private function getControllerFor(User $user): array
    {
        $controller = new Controller();
        [$request, $session] = $this->getRequestWithSession();
        $request->setUserResolver(static fn () => $user);

        return [$controller, $request, $session];
    }

    private function getRequestWithSession(array $query = [], array $request = []): array
    {
        $request = new Request($query, $request);
        $session = new Store('session', new SessionHandler());
        $request->setLaravelSession($session);

        return [$request, $session];
    }

    private function getRequest(array $query = [], array $request = []): Request
    {
        $request = new Request($query, $request);
        $session = new Store('session', new SessionHandler());
        $request->setLaravelSession($session);

        return $request;
    }

    private function getDashboardFor(User $user): Response
    {
        [$controller, $request] = $this->getControllerFor($user);

        return $controller->dashboard($request);
    }
}
