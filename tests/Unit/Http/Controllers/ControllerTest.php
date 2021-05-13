<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\Contact;
use App\Models\ApiAuthorization;
use App\Models\Plan;
use App\Models\User;
use App\View\Components\SubscriptionBilling;
use Carbon\CarbonImmutable;
use Carbon\Carbonite;
use Carbon\Carbonite\Attribute\Freeze;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\Store;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\View\Factory;
use Laravel\Cashier\Subscription as CashierSubscription;
use ReflectionMethod;
use SessionHandler;
use Stripe\Collection as StripeCollection;
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

    /**
     * @dataProvider getIncreaseLimitConfig
     */
    public function testIncreaseLimit(?string $expectedOldDomain, array $config): void
    {
        $originalConfig = config('app.authorizations');
        Config::set('app.authorizations', $config);
        $ziggy = $this->newZiggy();
        Auth::login($ziggy);
        $controller = new Controller();
        [$request, $session] = $this->getRequestWithSession();
        $response = $controller->increaseLimit($request, 'foo.bar.com');

        $this->assertSame('foo.bar.com', $session->get('increase-limit'));
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(route('dashboard')));

        [$controller, $request, $session] = $this->getControllerFor($ziggy, [], [], ['increase-limit' => 'foo.bar.com']);
        $controller->dashboard($request);

        $this->assertSame($expectedOldDomain, $session->getOldInput('domain'));

        Config::set('app.authorizations', $originalConfig);
    }

    public function getIncreaseLimitConfig(): iterable
    {
        yield ['foo.bar.com', ['domain', 'ip']];
        yield [null, []];
    }

    public function testCancelSubscribe(): void
    {
        $controller = new Controller();
        $response = $controller->cancelSubscribe('pro');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(route('plan')));
        $this->assertSame([
            'canceled' => 'pro',
            '_flash' => [
                'new' => ['canceled'],
                'old' => [],
            ]
        ], $response->getSession()->all());
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

    public function testDashboard(): void
    {
        $ziggy = $this->newZiggy();
        Auth::login($ziggy);
        $dashboard = $this->getDashboardFor($ziggy, [], [], ['property' => 'foo.bar.com'])->getContent();

        $this->assertStringContainsString('value="bar.com"', $dashboard);
        $this->assertStringContainsString('name="name" value="bar"', $dashboard);
        $this->assertStringContainsString('value="domain" checked', $dashboard);

        $dashboard = $this->getDashboardFor($ziggy, ['property' => '12.52.63.2'])->getContent();

        $this->assertStringContainsString('value="12.52.63.2"', $dashboard);
        $this->assertStringContainsString('name="name" value="Serveur"', $dashboard);
        $this->assertStringContainsString('value="ip" checked', $dashboard);

        $ziggy->apiAuthorizations()->create([
            'name'  => 'Ziggy site',
            'type'  => 'domain',
            'value' => 'ziggy.com',
        ]);
        $ziggy = $this->reloadUser($ziggy);

        $kt = $this->newUser('KT Tunstall', 'hard.girl@tunstall.com');
        $kt->apiAuthorizations()->create([
            'name'  => 'KT Tunstall',
            'type'  => 'domain',
            'value' => 'tunstall.com',
        ]);
        $kt = $this->reloadUser($kt);

        $dashboard = $this->getDashboardFor($ziggy)->getContent();

        $this->assertStringContainsString('Ziggy site', $dashboard);
        $this->assertStringContainsString('ziggy.com', $dashboard);
        $this->assertStringNotContainsString('KT Tunstall', $dashboard);
        $this->assertStringNotContainsString('tunstall.com', $dashboard);

        $dashboard = $this->getDashboardFor($ziggy, [], [], [], (string) $kt->id)->getContent();

        $this->assertStringContainsString('Ziggy site', $dashboard);
        $this->assertStringContainsString('ziggy.com', $dashboard);
        $this->assertStringNotContainsString('KT Tunstall', $dashboard);
        $this->assertStringNotContainsString('tunstall.com', $dashboard);

        $ziggy->email = config('app.super_admin');
        $dashboard = $this->getDashboardFor($ziggy, [], [], [], (string) $kt->id)->getContent();

        $this->assertStringContainsString('KT Tunstall', $dashboard);
        $this->assertStringContainsString('tunstall.com', $dashboard);
        $this->assertStringNotContainsString('Ziggy site', $dashboard);
        $this->assertStringNotContainsString('ziggy.com', $dashboard);
    }

    #[Freeze]
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

        $yesterday = CarbonImmutable::yesterday();
        $yesterdayDate = $yesterday->format('Y-m-d');
        $beforeYesterdayDate = $yesterday->subDay()->format('Y-m-d');

        DB::table('daily_filtered_log')
            ->where('key', 'domain')
            ->whereIn('value', ['biz.github.io', 'web.github.io'])
            ->delete();
        DB::table('daily_filtered_log')->insert([
            [
                'key' => 'domain',
                'value' => 'biz.github.io',
                'date' => $beforeYesterdayDate,
                'count' => 2536,
            ],
            [
                'key' => 'domain',
                'value' => 'web.github.io',
                'date' => $beforeYesterdayDate,
                'count' => 1458,
            ],
            [
                'key' => 'domain',
                'value' => 'biz.github.io',
                'date' => $yesterdayDate,
                'count' => 4242,
            ],
            [
                'key' => 'domain',
                'value' => 'web.github.io',
                'date' => $yesterdayDate,
                'count' => 9863,
            ],
        ]);

        $this->subscribePlan($ziggy, 'start', 'monthly');
        $ziggy = $this->reloadUser($ziggy);
        Auth::login($ziggy);
        $content = $this->getDashboardFor($ziggy)->getContent();

        $this->assertSame(1, preg_match('/data-graph="([^"]+)"/', $content, $match));

        $hits = json_decode(htmlspecialchars_decode($match[1]), true);

        $this->assertSame([
            'domain:web.github.io' => [
                $yesterday->subDays(30)->format('Y-m-d') => 0,
                $yesterday->subDays(29)->format('Y-m-d') => 0,
                $yesterday->subDays(28)->format('Y-m-d') => 0,
                $yesterday->subDays(27)->format('Y-m-d') => 0,
                $yesterday->subDays(26)->format('Y-m-d') => 0,
                $yesterday->subDays(25)->format('Y-m-d') => 0,
                $yesterday->subDays(24)->format('Y-m-d') => 0,
                $yesterday->subDays(23)->format('Y-m-d') => 0,
                $yesterday->subDays(22)->format('Y-m-d') => 0,
                $yesterday->subDays(21)->format('Y-m-d') => 0,
                $yesterday->subDays(20)->format('Y-m-d') => 0,
                $yesterday->subDays(19)->format('Y-m-d') => 0,
                $yesterday->subDays(18)->format('Y-m-d') => 0,
                $yesterday->subDays(17)->format('Y-m-d') => 0,
                $yesterday->subDays(16)->format('Y-m-d') => 0,
                $yesterday->subDays(15)->format('Y-m-d') => 0,
                $yesterday->subDays(14)->format('Y-m-d') => 0,
                $yesterday->subDays(13)->format('Y-m-d') => 0,
                $yesterday->subDays(12)->format('Y-m-d') => 0,
                $yesterday->subDays(11)->format('Y-m-d') => 0,
                $yesterday->subDays(10)->format('Y-m-d') => 0,
                $yesterday->subDays(9)->format('Y-m-d') => 0,
                $yesterday->subDays(8)->format('Y-m-d') => 0,
                $yesterday->subDays(7)->format('Y-m-d') => 0,
                $yesterday->subDays(6)->format('Y-m-d') => 0,
                $yesterday->subDays(5)->format('Y-m-d') => 0,
                $yesterday->subDays(4)->format('Y-m-d') => 0,
                $yesterday->subDays(3)->format('Y-m-d') => 0,
                $yesterday->subDays(2)->format('Y-m-d') => 0,
                $beforeYesterdayDate => 1458,
                $yesterdayDate => 9863,
            ],
        ], $hits);

        $this->assertStringContainsString(
            'Terminer l&#039;abonnement (annuler le renouvellement à échéance).',
            $content,
        );
        $this->assertStringNotContainsString(
            'Activer le renouvellement automatique',
            $content,
        );

        [$controller, $request] = $this->getControllerFor($ziggy);
        /** @var \Illuminate\View\View $view */
        $view = $controller->plan($request);

        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
        $this->assertSame('plan', $view->getName());
        $data = $view->getData();
        unset($data['plans']);
        $this->assertSame([
            'user' => $ziggy,
            'credit' => 9.9,
            'creditCurrency' => null,
            'closureFees' => null,
            'stripeKey' => config('stripe.publishable_key'),
            'numberOfPlans' => 3,
            'currentPlanId' => 'start',
            'currentRecurrence' => 'monthly',
            'selectedPlan' => null,
            'selectedRecurrence' => null,
            'selectedCard' => null,
            'canceled' => null,
            'paymentError' => null,
        ], $data);

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
        $view = $controller->plan($request);

        $this->assertStringContainsString('ou 199,00 € / an', $view->render());

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

    public function testBillingPortal(): void
    {
        $ziggy = $this->newZiggy();
        Auth::login($ziggy);

        [$controller, $request] = $this->getControllerFor($ziggy);
        /** @var RedirectResponse $redirection */
        $redirection = $controller->billingPortal($request);

        $this->assertInstanceOf(RedirectResponse::class, $redirection);
        $this->assertMatchesRegularExpression(
            '`^https://billing\.stripe\.com/session/[A-Za-z0-9_-]+$`',
            $redirection->getTargetUrl(),
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

    public function testPaymentError(): void
    {
        $ziggy = $this->newZiggy();
        Auth::login($ziggy);
        $paymentMethod = $this->getPaymentMethod('4000000000000002');
        [$controller, $request] = $this->getControllerFor($ziggy, [
            'plan' => 'pro',
            'recurrence' => 'monthly',
            'card' => '4000000000000002',
            'stripePaymentMethod' => $paymentMethod->id,
        ]);

        $redirection = $controller->subscribe($request);
        $this->assertInstanceOf(RedirectResponse::class, $redirection);
        $this->assertTrue($redirection->isRedirect(route('plan')));
        $expectedSession = [
            'selectedPlan' => 'pro',
            '_flash' => [
                'new' => [
                    'selectedPlan',
                    'selectedRecurrence',
                    'selectedCard',
                    'paymentError'
                ],
                'old' => [],
            ],
            'selectedRecurrence' => 'monthly',
            'selectedCard' => '4000000000000002',
            'paymentError' => 'Your card was declined.',
        ];
        $this->assertSame(
            $expectedSession,
            Arr::only($redirection->getSession()->all(), array_keys($expectedSession)),
        );
        Auth::logout();
    }

    public function testSubscribeClearCache(): void
    {
        $ziggy = $this->newZiggy();
        $ziggy->apiAuthorizations()->create([
            'name' => 'Music',
            'type' => 'domain',
            'value' => 'music.github.io',
        ])->verify();
        $dataFile = __DIR__ . '/../../../../data/properties/domain/music.github.io.php';
        file_put_contents($dataFile, '<?php return [];');
        $graceFile = __DIR__ . '/../../../../data/properties-grace/music.github.io.txt';
        file_put_contents($graceFile, '2');

        Auth::login($ziggy);
        $paymentMethod = $this->getPaymentMethod();
        [$controller, $request] = $this->getControllerFor($ziggy, [
            'plan' => 'pro',
            'recurrence' => 'monthly',
            'cardChoice' => '4242424242424242',
            'stripePaymentMethod' => $paymentMethod->id,
        ]);
        $controller->subscribe($request);

        $this->assertFileDoesNotExist($dataFile);
        $this->assertFileDoesNotExist($graceFile);

        Auth::logout();
    }

    public function testSubscribeUnknownRecurrence(): void
    {
        $ziggy = $this->newZiggy();
        Auth::login($ziggy);
        $paymentMethod = $this->getPaymentMethod();
        [$controller, $request] = $this->getControllerFor($ziggy, [
            'plan' => 'pro',
            'recurrence' => 'weekly',
            'cardChoice' => '4242424242424242',
            'stripePaymentMethod' => $paymentMethod->id,
        ]);
        /** @var RedirectResponse $redirection */
        $redirection = $controller->subscribe($request);

        $this->assertInstanceOf(RedirectResponse::class, $redirection);
        $this->assertTrue($redirection->isRedirect(route('plan')));
        $this->assertSame(
            "Veuillez sélectionner l'offre mensuelle ou annuelle d'un des abonnements.",
            $redirection->getSession()->get('paymentError'),
        );

        $ziggy = $this->newZiggy();
        Auth::login($ziggy);
        $paymentMethod = $this->getPaymentMethod();
        [$controller] = $this->getControllerFor($ziggy, [
            'plan' => 'pro',
            'recurrence' => 'monthly',
            'cardChoice' => '4242424242424242',
            'stripePaymentMethod' => $paymentMethod->id,
        ]);
        $recordSubscription = new ReflectionMethod(Controller::class, 'recordSubscription');
        $recordSubscription->setAccessible(true);
        $subscription = $recordSubscription->invoke(
            $controller,
            $ziggy,
            'pro',
            'monthly',
            $paymentMethod->id,
            '4242424242424242',
        );

        $this->assertInstanceOf(CashierSubscription::class, $subscription);
    }

    public function testConfirmIntent(): void
    {
        $stripe = $this->getStripeClient();
        $ziggy = $this->newZiggy();
        Auth::login($ziggy);
        $paymentMethod = $this->getPaymentMethod('4000000000003220');
        [$controller, $request, $session] = $this->getControllerFor($ziggy, [
            'plan' => 'pro',
            'recurrence' => 'monthly',
            'card' => '4000000000003220',
            'stripePaymentMethod' => $paymentMethod->id,
        ]);

        $redirection = $controller->subscribePlan($request, 'not-exist');
        $this->assertInstanceOf(RedirectResponse::class, $redirection);
        $this->assertTrue($redirection->isRedirect(route('plan')));
        $expectedSession = [
            'selectedPlan' => 'not-exist',
            '_flash' => [
                'new' => [
                    'selectedPlan',
                    'selectedRecurrence',
                    'canceled',
                    'selectedCard',
                ],
                'old' => [],
            ],
            'selectedRecurrence' => 'monthly',
            'canceled' => 'not-exist',
            'selectedCard' => '4000000000003220',
        ];
        $this->assertSame(
            $expectedSession,
            Arr::only($redirection->getSession()->all(), array_keys($expectedSession)),
        );

        $intentView = $controller->subscribe($request);
        $this->assertInstanceOf(\Illuminate\View\View::class, $intentView);
        $this->assertSame('payment-authentication', $intentView->getName());
        $data = $intentView->getData();
        $this->assertSame($ziggy, Arr::pull($data, 'user'));
        $id = Arr::pull($data, 'intent')->id;
        $this->assertSame([], $data);
        $intentData = $session->get("intent-data-$id");
        $this->assertIsArray($intentData);

        $stripe->paymentIntents->confirm($id);

        [$controller, $request] = $this->getControllerFor($ziggy, ['intent' => $id], [], [
            "intent-data-$id" => $intentData
        ]);
        $intentView = $controller->confirmIntent($request);

        $this->assertInstanceOf(\Illuminate\View\View::class, $intentView);
        $this->assertSame('payment-authentication', $intentView->getName());

        $paymentMethod = $this->getPaymentMethod();
        [$controller, $request] = $this->getControllerFor($ziggy, ['intent' => $id], [], [
            "intent-data-$id" => array_merge($intentData, [
                'cardChoice' => '4242424242424242',
                'stripePaymentMethod' => $paymentMethod->id,
            ]),
        ]);
        $redirection = $controller->confirmIntent($request);

        $this->assertInstanceOf(RedirectResponse::class, $redirection);
        $this->assertTrue($redirection->isRedirect(route('dashboard')));
        $ziggy = $this->reloadUser($ziggy);
        $this->assertSame(config('plan.pro.id'), $ziggy->getActiveSubscription()['plan']['product']);
        Auth::logout();
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
                    'selectedPlan',
                    'selectedRecurrence',
                    'selectedCard',
                    'paymentError',
                ],
                'old' => [],
            ],
            'selectedRecurrence' => 'monthly',
            'selectedCard' => '42424242',
            'paymentError' => 'Refused payment',
        ], $response->getSession()->all());
    }

    #[Freeze]
    public function testGetPlansCredit(): void
    {
        $getPlansCredit = new ReflectionMethod(Controller::class, 'getPlansCredit');
        $getPlansCredit->setAccessible(true);
        $ziggy = $this->newZiggy();
        $ziggy->createAsStripeCustomer();
        $paymentMethod = $this->getPaymentMethod();
        $ziggy->addPaymentMethod($paymentMethod);

        $this->assertSame(0.0, $getPlansCredit->invoke(new Controller(), $ziggy));

        $ziggy->charge(2500, $paymentMethod);
        $payment = $ziggy->charge(499, $paymentMethod);

        $this->assertSame(29.99, $getPlansCredit->invoke(new Controller(), $ziggy));
        Auth::login($ziggy);
        $this->assertSame(29.99, $getPlansCredit->invoke($this->getControllerFor($ziggy)[0]));

        $ziggy->refund($payment->id);

        $this->assertSame(25.0, $getPlansCredit->invoke($this->getControllerFor($ziggy)[0]));

        $this->subscribePlan($ziggy, 'premium', 'yearly');

        $this->assertSame(374.0, $getPlansCredit->invoke($this->getControllerFor($ziggy)[0]));

        $ziggy = $this->reloadUser($ziggy);
        Auth::login($ziggy);

        $this->assertSame(374.0, $getPlansCredit->invoke($this->getControllerFor($ziggy)[0]));

        Carbonite::elapse('6 months');

        $this->assertSame(200.0, round($getPlansCredit->invoke($this->getControllerFor($ziggy)[0]), -1));
    }

    public function testGetNextCounterReset(): void
    {
        $getNextCounterResetMethod = new ReflectionMethod(Controller::class, 'getNextCounterReset');
        $getNextCounterResetMethod->setAccessible(true);
        $controller = new Controller();
        $getNextCounterReset = static fn ($date) => $getNextCounterResetMethod->invoke(
            $controller,
            CarbonImmutable::parse($date),
        );

        Carbonite::freeze('2021-02-27 14:42:33');

        $this->assertSame('lundi à 00:00', $getNextCounterReset('2021-01-30 20:50:12'));
        $this->assertSame('lundi à 20:50', $getNextCounterReset('2021-01-01 20:50:12'));
        $this->assertSame('15/03/2021', $getNextCounterReset('2021-01-15 20:50:12'));
        $this->assertSame('15/03/2021', $getNextCounterReset('2021-02-15 20:50:12'));
        $this->assertSame('Demain à 20:50', $getNextCounterReset('2021-01-28 20:50:12'));

        Carbonite::freeze('2021-03-31 14:42:33');

        $this->assertSame('Aujourd’hui à 18:24', $getNextCounterReset('2021-01-31 18:24:58'));
        $this->assertSame('01/05/2021', $getNextCounterReset('2021-01-31 12:24:58'));
    }

    public function testGetUserCharges(): void
    {
        $getUserChargesMethod = new ReflectionMethod(Controller::class, 'getUserCharges');
        $getUserChargesMethod->setAccessible(true);
        $controller = new Controller();
        $getUserCharges = static fn (...$args) => $getUserChargesMethod->invoke($controller, ...$args);

        $ziggy = $this->newZiggy();
        $customerId = $ziggy->createAsStripeCustomer()->id;
        $paymentMethod = $this->getPaymentMethod();
        $ziggy->addPaymentMethod($paymentMethod);
        $ziggy->charge(499, $paymentMethod);

        $collection = $getUserCharges(null);

        $this->assertIsIterable($collection);
        $this->assertCount(0, $collection);

        $collection = $getUserCharges($customerId);

        $this->assertIsIterable($collection);
        $this->assertCount(1, $collection);
        $this->assertSame(499, iterator_to_array($collection)[0]['amount']);
    }

    /**
     * @param User $user
     *
     * @return array{Controller, Request, Store}
     */
    private function getControllerFor(
        User $user,
        array $query = [],
        array $request = [],
        array $sessionData = []
    ): array {
        $controller = new Controller();
        [$request, $session] = $this->getRequestWithSession($query, $request, $sessionData);
        $request->setUserResolver(static fn () => $user);

        return [$controller, $request, $session];
    }

    private function getRequestWithSession(array $query = [], array $request = [], array $sessionData = []): array
    {
        $request = new Request($query, $request);
        $session = new Store('session', new SessionHandler());
        $session->put($sessionData);
        $request->setLaravelSession($session);

        return [$request, $session];
    }

    private function getRequest(array $query = [], array $request = [], array $sessionData = []): Request
    {
        [$request] = $this->getRequestWithSession($query, $request, $sessionData);

        return $request;
    }

    private function getDashboardFor(
        User $user,
        array $query = [],
        array $request = [],
        array $sessionData = [],
        ?string $userId = null
    ): Response {
        [$controller, $request] = $this->getControllerFor($user, $query, $request, $sessionData);

        return $controller->dashboard($request, $userId);
    }
}
