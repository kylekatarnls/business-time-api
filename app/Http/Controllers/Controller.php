<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authorization\AuthorizationFactory;
use App\Exceptions\PlanOfferException;
use App\Mail\Contact;
use App\Mail\PlanChange;
use App\Models\ApiAuthorization;
use App\Models\Plan;
use App\Models\User;
use App\Util\Number;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\Store;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Laravel\Cashier\Exceptions\PaymentActionRequired;
use Laravel\Cashier\Exceptions\PaymentFailure;
use Laravel\Cashier\Subscription as CashierSubscription;
use Stripe\Charge;
use Stripe\Collection as StripeCollection;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
use Stripe\SubscriptionItem;

class Controller extends AbstractController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    private ?StripeClient $stripeClient = null;

    public function home(): RedirectResponse
    {
        return redirect('dashboard');
    }

    public function contact(Request $request): View
    {
        $session = $request->session();

        return \view('contact', [
            'sent' => $session->pull('sent'),
            'email' => old('email') ?: Auth::user()?->email,
        ]);
    }

    public function exonerate(Request $request): RedirectResponse|View
    {
        $session = $request->session();
        /** @var User $user */
        $user = Auth::user();
        $properties = $user?->getAuthorizations();

        if (empty($properties)) {
            return redirect('dashboard')->with('errors', [
                __('You need first to register your IP or domain then use the validation to confirm you own it.'),
            ]);
        }

        return \view('contact', [
            'template' => 'exonerate',
            'selectedProperties' => explode(',', (string) $request->input('properties')),
            'properties' => $properties,
            'sent' => $session->pull('sent'),
            'email' => old('email') ?: $user->email,
        ]);
    }

    public function postContact(Request $request): RedirectResponse
    {
        $subject = $request->get('template') === 'exonerate'
            ? __('Exoneration request submitted')
            : null;

        $route = $request->get('template') === 'exonerate'
            ? 'exonerate'
            : 'contact';

        $email = Auth::user()?->email;

        $this->sendMail(
            $request->get('email') ?: $email,
            new Contact([
                'content' => $request->get('message'),
            ], $subject),
            [
                'template' => $request->get('template'),
                'properties' => $request->get('properties'),
                'content' => ($email ? $email . "\n\n" : '') .
                    $request->get('email') . "\n\n" .
                    $request->get('message'),
            ],
        );

        return redirect()->route($route)->with('sent', true);
    }

    public function increaseLimit(Request $request, string $ipOrDomain): RedirectResponse
    {
        $request->session()->put('increase-limit', $ipOrDomain);

        return $this->home();
    }

    public function dashboard(Request $request, ?string $userId = null): Response
    {
        /** @var User $user */
        $user = $request->user();
        $onBehalf = $userId !== null && $user->isSuperAdmin();

        if ($onBehalf) {
            $user = User::find($userId);
        }

        $types = config('app.authorizations');
        $session = $request->session();
        $property = $request->query->get('property') ?: $session->get('property');
        $isIP = $property && preg_match('/^\d/', $property);
        $subDomain = $isIP ? null : $property;
        $ip = $isIP ? $property : null;
        $domain = $subDomain && preg_match('/^(?:.+)?\.([^.]+\.[a-z]+)$/', $subDomain, $match)
            ? $match[1]
            : null;
        $defaultValues = compact('ip', 'domain');
        $this->prefillDashboardAuthorization($types, $session);
        $authorizations = array_map(
            fn(string $type) => $this->getApiAuthorizationsData($type, $user, $defaultValues[$type] ?? null),
            $types,
        );
        $defaultAuthorization =
            Arr::first($authorizations, static fn(object $data) => $session->hasOldInput($data->type)) ?:
                $authorizations[0];
        $defaultAuthorization->default = true;
        $plans = Plan::getPlansData();
        $planId = $user->getPlanId(array_keys($plans));
        $nextBill = '';
        $nextBillDateTime = '';
        $nextCounterReset = '';
        $subscription = $user->getActiveSubscription();

        if ($subscription) {
            if (isset($subscription['current_period_end'])) {
                $nextBillCarbon = CarbonImmutable::createFromTimeStamp($subscription['current_period_end']);
                $nextBill = $nextBillCarbon->calendar();
                $nextBillDateTime = $nextBillCarbon->isoFormat('L LTS');
            }

            if (isset($subscription['created'])) {
                $nextCounterReset = $this->getNextCounterReset(
                    CarbonImmutable::createFromTimeStamp($subscription['created'])
                );
            }
        }

        $paidRequests = $user->getPaidRequests();
        $limit = $user->getLimit($plans[$planId] ?? null);

        $view = ResponseFacade::view('dashboard', [
            'user'                  => $user,
            'onBehalf'              => $onBehalf,
            'domain'                => $domain,
            'name'                  => old('name') ?: ($isIP
                ? 'Server'
                : preg_replace('/\.[a-z]+$/', '', $domain ?: '')),
            'subDomain'             => $subDomain,
            'ip'                    => $ip,
            'property'              => $property,
            'isIP'                  => $isIP,
            'type'                  => old('type') ?: ($isIP ? 'ip' : null),
            'planId'                => $planId,
            'plan'                  => $plans[$planId] ?? null,
            'limit'                 => $limit,
            'freeLimit'             => $this->getFreePlan()['limit'],
            'paidRequests'          => $paidRequests,
            'percentage'            => $limit ? min(1, $paidRequests / $limit) * 100 : null,
            'nextBill'              => [
                'end'          => $subscription?->cancel_at
                    ? CarbonImmutable::createFromTimestamp($subscription->cancel_at)->calendar()
                    : null,
                'date'         => $nextBill,
                'dateTime'     => $nextBillDateTime,
                'subscription' => $subscription?->id,
            ],
            'nextCounterReset'      => $nextCounterReset,
            'month'                 => CarbonImmutable::now()->monthName,
            'authorizations'        => $authorizations,
            'authorizationsCount'   => array_sum(array_map(static fn ($data) => count($data->list), $authorizations)),
            'authorisationsErrors'  => (array) $session->pull('authorisationsErrors', []),
            'verifyError'           => $session->pull('verifyError'),
            'verifiedAuthorization' => $session->pull('verifiedAuthorization'),
            'errors'                => (array) $session->pull('errors', []),
            'hasVerifiedProperties' => $user->hasVerifiedProperties(),
            'confirmingSubscriptionCancellation' => false,
        ]);

        if (!$request->cookie('vuid')) {
            $view->headers->setCookie(
                Cookie::forever('vuid', $user->email),
            );
        }

        return $view;
    }

    public function autorenew(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $subscription = $user->getActiveSubscription();

        if ($subscription?->cancel_at) {
            $user
                ->subscriptions
                ->where('stripe_id', $subscription->id)
                ->first()
                ->resume();

            Log::info('User ' . $user->id . ' re-enabled autorenew for ' . $subscription->id);
        }

        return redirect('dashboard');
    }

    public function plan(Request $request): View
    {
        $plans = $this->getPlans();
        /** @var User $user */
        $user = $request->user();

        $activeSubscription = $user->getActiveSubscription();
        $credit = 0.0;

        if ($activeSubscription) {
            $items = array_map(static fn(SubscriptionItem $item) => [
                'id' => $item->id,
                'price' => $item->price->id,
            ], iterator_to_array($activeSubscription->items));
            $invoice = Invoice::upcoming([
                'customer'                    => $activeSubscription->customer,
                'subscription'                => $activeSubscription->id,
                'subscription_items'          => $items,
                'subscription_proration_date' => time() + 600,
            ]);

            $remainingCreditCents = (int) $invoice['amount_remaining'] ?? 0;

            if ($remainingCreditCents > 0) {
                $credit = 0.01 * $remainingCreditCents;
            }
        }

        $session = $request->session();
        $currentPlan = Arr::first(Arr::where($plans, static fn (array $plan) => $plan['subscribed']));
        $closureFees = $this->getClosureFees();

        return \view('plan', [
            'user'               => $user,
            'credit'             => $credit,
            'creditCurrency'     => null,
            'closureFees'        => $closureFees ? Number::format($this->getClosureFees(), 2) : null,
            'stripeKey'          => config('stripe.publishable_key'),
            'numberOfPlans'      => count($plans),
            'plans'              => $plans,
            'currentPlanId'      => $currentPlan['key'] ?? null,
            'currentRecurrence'  => $currentPlan['recurrence'] ?? null,
            'selectedPlan'       => $session->pull('selectedPlan'),
            'selectedRecurrence' => $session->pull('selectedRecurrence'),
            'selectedCard'       => $session->pull('selectedCard'),
            'canceled'           => $session->pull('canceled'),
            'paymentError'       => $session->pull('paymentError'),
        ]);
    }

    public function subscribe(Request $request): RedirectResponse|View
    {
        return $this->subscribePlan($request, $request->input('plan'));
    }

    public function subscribePlan(Request $request, string $planId): RedirectResponse|View
    {
        return $this->doSubscribePlan($request->user(), $request->session(), [
            'planId' => $planId,
            'recurrence' => $request->input('recurrence'),
            'cardChoice' => $request->input('card'),
            'stripePaymentMethod' => $request->input('stripePaymentMethod'),
        ]);
    }

    public function confirmIntent(Request $request): RedirectResponse|View
    {
        $intentId = $request->input('intent');
        $session = $request->session();

        return $this->doSubscribePlan($request->user(), $session, $session->get('intent-data-' . $intentId));
    }

    public function rejectIntent(Request $request): RedirectResponse
    {
        $intentId = $request->input('intent');
        $session = $request->session();
        [
            'planId' => $planId,
            'recurrence' => $recurrence,
            'cardChoice' => $cardChoice,
        ] = $session->get('intent-data-' . $intentId);

        return $this->goToPlan([
            'selectedPlan' => $planId,
            'selectedRecurrence' => $recurrence,
            'selectedCard' => $cardChoice,
            'paymentError' => $request->input('error'),
        ]);
    }

    public function doSubscribePlan(User $user, Store $store, array $data): RedirectResponse|View
    {
        $user->last_subscribe_at = now();
        $user->save();

        $plans = $this->getPlans();
        [
            'planId' => $planId,
            'recurrence' => $recurrence,
            'cardChoice' => $cardChoice,
            'stripePaymentMethod' => $stripePaymentMethod,
        ] = $data;
        $plan = $plans[$planId] ?? null;

        if (!$plan) {
            return $this->goToPlan([
                'selectedPlan' => $planId,
                'selectedRecurrence' => $recurrence,
                'canceled' => $planId,
                'selectedCard' => $cardChoice,
            ]);
        }

        try {
            $this->recordSubscription(
                $user,
                $planId,
                $recurrence,
                $stripePaymentMethod,
                $cardChoice ?? 'new',
            );

            foreach ($user->getAuthorizations() as $authorization) {
                $value = $authorization->value;
                self::clearCache($authorization->type, $value);
                @unlink(__DIR__ . "/../../../data/properties-grace/$value.txt") ?: false;
            }
        } catch (PaymentActionRequired $actionRequired) {
            Log::notice($actionRequired);

            $intent = $actionRequired->payment->asStripePaymentIntent();
            $store->put('intent-data-' . $intent->id, $data);

            return \view('payment-authentication', [
                'user' => $user,
                'intent' => $intent,
            ]);
        } catch (InvalidRequestException | PaymentFailure | PlanOfferException $exception) {
            Log::critical($exception);

            return $this->goToPlan([
                'selectedPlan' => $planId,
                'selectedRecurrence' => $recurrence,
                'selectedCard' => $cardChoice,
                'paymentError' => $exception->getMessage(),
            ]);
        }

        return $this->home();
    }

    public function cancelSubscribe(string $plan): RedirectResponse
    {
        return redirect('subscribe-cancel')->with('canceled', $plan);
    }

    public function billingPortal(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->createOrGetStripeCustomer();

        return $user->redirectToBillingPortal();
    }

    /**
     * @param User $user
     * @param string $planId
     * @param string $recurrence
     * @param array $paymentData
     *
     * @return CashierSubscription
     *
     * @throws InvalidRequestException
     * @throws PaymentActionRequired
     * @throws PaymentFailure
     * @throws ApiErrorException
     * @throws PlanOfferException
     */
    protected function recordSubscription(
        User $user,
        string $planId,
        string $recurrence,
        ?string $stripePaymentMethod,
        string $cardChoice
    ): CashierSubscription {
        $planOffer = config("plan.$planId.price.$recurrence");

        if (!$planOffer) {
            throw new PlanOfferException(
                __('Please select the monthly or yearly offer of one of the plans.'),
            );
        }

        if (!$user->hasStripeId()) {
            $user->createAsStripeCustomer();
        }

        if ($cardChoice !== 'default' && $stripePaymentMethod) {
            $user->updateDefaultPaymentMethod($stripePaymentMethod);
        }

        $subscription = $this->initializeSubscription($user, $planId, $planOffer);
        $plansData = Plan::getPlansData();
        $mailContent = __(':plan plan subscribed on :frequency basis.', [
            'plan' => $plansData[$planId]['name'],
            'frequency' => $recurrence === 'monthly' ? __('monthly') : __('yearly'),
        ]);
        // $keys = array_keys($plansData);
        // $refundAmount = $this->getPlansCredit($user, $keys);
        // $user->addBalance($refundAmount);
        // $mailContent .= "\n\n" . __(':amount from the ongoing previous subscription will be deduced from next payments.', [
        //     'amount' => price(Number::format($refundAmount, 2)),
        // ]);

        $this->sendMailSilently(
            $user->email,
            new PlanChange([
                'content' => $mailContent,
            ]),
            [
                'properties' => [
                    'user' => $user->email,
                    'plan' => $planId,
                    'recurrence' => $recurrence,
                ],
            ],
        );

        return $subscription;
    }

    private function getNextCounterReset(CarbonImmutable $creation): string
    {
        $now = now();

        if ($creation->day > $now->daysInMonth) {
            return $now->startOfMonth()->addMonth()->calendar();
        }

        $date = $now->copy()->setDateTime(
            // Year and month from current date
            $now->year, $now->month,
            // Day and time from creation date-time
            $creation->day, $creation->hour, $creation->minute, $creation->second, $creation->microsecond,
        );

        if ($date < $now) {
            $date = $date->addMonth();
        }

        if ($date->day !== $creation->day) {
            $date = $date->startOfMonth();
        }

        return $date->calendar();
    }

    private function getStripeClient(): StripeClient
    {
        if ($this->stripeClient === null) {
            $this->stripeClient = new StripeClient([
                'api_key' => config('stripe.secret_key'),
            ]);
        }

        return $this->stripeClient;
    }

    private function getFreePlan(): Plan
    {
        static $data = null;

        if ($data === null) {
            $data = Plan::fromId('free');
        }

        return $data;
    }

    private function getGuestPlan(): Plan
    {
        static $data = null;

        if ($data === null) {
            $data = Plan::fromId('guest');
        }

        return $data;
    }

    private function getApiAuthorizationsData(string $type, ?User $user = null, $defaultValue = null): object
    {
        return (object) [
            'type'  => $type,
            'name'  => $this->getApiAuthorizationName($type),
            'list'  => $this->getApiAuthorizationsByType($type, $user),
            'value' => old($type) ?? $defaultValue,
        ];
    }

    private function getApiAuthorizationName(string $type): string
    {
        return AuthorizationFactory::fromType($type)->getName();
    }

    private function getApiAuthorizationsByType(string $type, ?User $user = null): Collection
    {
        static $apiAuthorizations = null;

        if ($apiAuthorizations === null) {
            $apiAuthorizations = $this->getApiAuthorizations($user);
        }

        return $apiAuthorizations
            ->filter(static fn(ApiAuthorization $apiAuthorization) => $apiAuthorization->type === $type);
    }

    /**
     * @param User $user
     * @param string $planId
     * @param string $planOffer
     * @param string|null $paymentMethod
     *
     * @return CashierSubscription
     *
     * @throws InvalidRequestException
     * @throws PaymentActionRequired
     * @throws PaymentFailure
     */
    private function initializeSubscription(
        User $user,
        string $planId,
        string $planOffer,
        ?string $paymentMethod = null
    ): CashierSubscription {
        $user->cancelSubscriptionsSilently();

//        $activeSubscription = $user->getActiveSubscription();
//
//        if ($activeSubscription instanceof CashierSubscription) {
//            $activeSubscription->name = $planId;
//            $user->clearActiveSubscriptionCache();
//
//            return $activeSubscription->swap($planId);
//        }

        return $user->subscribe($planId, $planOffer, $paymentMethod);
    }

    private function prefillDashboardAuthorization(array $types, Store $session): void
    {
        if (!$session->has('increase-limit') || $session->hasOldInput()) {
            return;
        }

        $ipOrDomain = $session->get('increase-limit');

        foreach ($types as $type) {
            if (AuthorizationFactory::fromType($type)->accept($ipOrDomain)) {
                $session->put("_old_input.$type", $ipOrDomain);

                return;
            }
        }
    }

    private function goToPlan(...$with): RedirectResponse
    {
        return redirect('plan')->with(...$with);
    }

    /**
     * @return StripeCollection|Charge[]
     */
    private function getUserCharges(?string $customerId): StripeCollection
    {
        return $customerId
            ? $this->getStripeClient()->charges->all(['customer' => $customerId])
            : new StripeCollection();
    }

    /**
     * @return StripeCollection|PaymentIntent[]
     */
    private function getUserPaymentsIntents(?string $customerId): StripeCollection
    {
        return $customerId
            ? $this->getStripeClient()->paymentIntents->all(['customer' => $customerId])
            : new StripeCollection();
    }

    /**
     * @return StripeCollection|Refund[]
     */
    private function getUserRefunds(?string $customerId): StripeCollection
    {
        return $customerId
            ? $this->getStripeClient()->refunds->all()
            : new StripeCollection();
    }

    /**
     * Remaining credits in Euros.
     *
     * @param User|null $user
     * @param string[]|null $keys
     *
     * @return float
     */
    private function getPlansCredit(?User $user = null, ?array $keys = null): float
    {
        $user = $user ?? $this->getUser();
        $keys = $keys ?? array_keys(Plan::getPlansData());
        $credit = 0.0;
        $customerId = $user?->stripeId();

        foreach ($this->getUserCharges($customerId) as $charge) {
            $credit += $charge->amount_received / 100;
        }

        foreach ($this->getUserPaymentsIntents($customerId) as $paymentIntent) {
            $credit += $paymentIntent->amount_received / 100;
        }

        foreach (($user?->getRefunds() ?? []) as $refund) {
            $credit -= $refund->getAmount();
        }

        foreach ($user->getSubscriptions($keys) as $subscription) {
            if ($subscription->active()) {
                $stripeSubscription = $subscription->asStripeSubscription();
                $start = CarbonImmutable::createFromTimestamp($stripeSubscription['current_period_start']);
                $end = CarbonImmutable::createFromTimestamp($stripeSubscription['current_period_end']);
                $total = (float) $end->floatDiffInSeconds($start);
                $remaining = (float) $end->floatDiffInSeconds();
                $credit += (float) $subscription->latestInvoice()->rawTotal() *
                    max(0, min(1, $remaining / $total));
            }
        }

        $credit -= $this->getClosureFees();

        return (float) max(0, ceil($credit) / 100);
    }

    /**
     * Closure fees in Euros.
     *
     * @return float
     */
    private function getClosureFees(): float
    {
        return 0.01 * config('app.closure_fees');
    }

    private function getPlans(): array
    {
        $sessions = $this->getStripeClient()->checkout->sessions;
        $plans = Plan::getPlansData();
        $keys = array_keys($plans);
        $user = $this->getUser();

        if ($user && !$user->hasStripeId()) {
            $user->createAsStripeCustomer();
        }

        return array_combine($keys, array_map(fn(string $key, Plan $data) => $data->with([
            'key'           => $key,
            'subscribed'    => $this->getUser()->subscribed($key),
            'recurrence'    => $this->getUser()->getSubscriptionRecurrence($key),
            'monthly_price' => $data->price(),
            'yearly_price'  => $data->price(0.1),
            'yearly_saving' => $data->price(0.02),
            'description'   => $data['limit'] && $data['limit'] < INF
                ? __('Up to :requests requests per month', [
                    'requests' => Number::format($data['limit']),
                ])
                : __('Unlimited'),
            'session'       => $sessions->create([
                'mode'                 => 'payment',
                'success_url'          => route('subscribe-plan', ['plan' => $key]),
                'cancel_url'           => route('subscribe-cancel', ['plan' => $key]),
                'customer'             => $user?->stripeId(),
                'payment_method_types' => ['card'],
                'line_items'           => [
                    [
                        'quantity'   => 1,
                        'price_data' => [
                            'product'     => $data['product'],
                            'unit_amount' => $data['price'],
                            'currency'    => $data['currency'],
                        ],
                    ],
                ],
            ]),
        ]), $keys, $plans));
    }
}
