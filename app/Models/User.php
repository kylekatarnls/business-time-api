<?php

declare(strict_types=1);

namespace App\Models;

use ArrayAccess;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription as CashierSubscription;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Stripe\Exception\ApiConnectionException;
use Stripe\Refund as StripeRefund;
use Stripe\Subscription;
use Throwable;

/**
 * @property int $id
 * @property string $email
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 * @property Carbon $last_subscribe_at
 * @property Carbon $email_verified_at
 * @property Collection<ApiAuthorization> apiAuthorizations
 * @property Collection<Refund> refunds
 * @property Collection<CashierSubscription> $subscriptions
 */
final class User extends Authenticatable
{
    use Billable {
        refund as billableRefund;
    }
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use SoftDeletes;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_subscribe_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_subscribe_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    protected ?Subscription $activeSubscription;

    protected bool $activeSubscriptionCached = false;

    public function apiAuthorizations(): HasMany
    {
        return $this->hasMany(ApiAuthorization::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function apiAuthorizationQuotaNotifications(): HasMany
    {
        return $this->hasMany(ApiAuthorizationQuotaNotification::class);
    }

    public function subscriptionQuotaNotifications(): HasMany
    {
        return $this->hasMany(SubscriptionQuotaNotification::class);
    }

    /**
     * @return ApiAuthorization[]
     */
    public function getAuthorizations()
    {
        /** @var ApiAuthorization[] $authorizations */
        $authorizations = $this->apiAuthorizations()->get();

        return $authorizations;
    }

    /**
     * @return Refund[]
     */
    public function getRefunds()
    {
        /** @var Refund[] $refunds */
        $refunds = $this->refunds()->get();

        return $refunds;
    }

    /**
     * @param string[] $keys
     *
     * @return CashierSubscription[]
     */
    public function getSubscriptions(?array $keys = null)
    {
        /** @var CashierSubscription[] $subscriptions */
        $subscriptions = $this->subscriptions->whereIn('name', $keys ?? array_keys(Plan::getPlansData()));

        return $subscriptions;
    }

    /**
     * @param string[] $keys
     *
     * @return CashierSubscription[]
     */
    public function getActiveSubscriptions(?array $keys = null)
    {
        /** @var Collection $subscriptions */
        $subscriptions = $this->getSubscriptions($keys);

        /** @var CashierSubscription[] $result */
        $result = $subscriptions->where('status', '!=', 'canceled');

        return $result;
    }

    /**
     * @param string[] $keys
     *
     * @return CashierSubscription[]
     */
    public function cancelSubscriptionsSilently(?array $keys = null): void
    {
        foreach ($this->getActiveSubscriptions($keys) as $subscription) {
            try {
                $subscription->cancelNow();
            } catch (Throwable $exception) {
                Log::notice($exception);
            }
        }
    }

    /**
     * @return Subscription[]
     */
    public function getCustomerSubscriptions()
    {
        for ($i = 0; $i < 3; $i++) {
            try {
                $customer = $this->asStripeCustomer();
            } catch (ApiConnectionException $exception) {
                $customer = null;
                Log::warning($exception);
            }

            if ($customer) {
                break;
            }

            sleep(1);
        }

        if (!$customer) {
            throw $exception;
        }

        /** @var Subscription[] $subscriptions */
        $subscriptions = $customer->subscriptions;

        return $subscriptions;
    }

    public function getSubscriptionRecurrence(string $planId): ?string
    {
        $subscription = $this->subscription($planId);

        if (!$subscription) {
            return null;
        }

        $plan = config("plan.$planId.price");

        if (!$plan) {
            return null;
        }

        return array_search($subscription->stripe_plan, $plan, true) ?: null;
    }

    public function addRefund(StripeRefund $refund)
    {
        $this->refunds()->create([
            'cents_amount' => $refund->amount,
            'currency' => $refund->currency,
            'stripe_refund_id' => $refund->id,
            'payment_intent' => $refund->payment_intent,
            'balance_transaction' => $refund->balance_transaction,
            'charge' => $refund->charge,
            'status' => $refund->status,
        ]);
    }

    public function refund($paymentIntent, array $options = []): StripeRefund
    {
        $stripeRefund = $this->billableRefund($paymentIntent, $options);

        $this->addRefund($stripeRefund);

        return $stripeRefund;
    }

    public function addBalance(float $credit): void
    {
        if ($credit < 0) {
            throw new InvalidArgumentException('Negative balance cannot be added, use subBalance() instead.');
        }

        if ($credit < 0.01) {
            return;
        }
    }

    public function getBalance(): float
    {
        return 0.01 * $this->asStripeCustomer()->balance;
    }

    public function subBalance(float $credit): void
    {
        if ($credit < 0) {
            throw new InvalidArgumentException('Negative balance cannot be subtracted, use addBalance() instead.');
        }

        if ($credit < 0.01) {
            return;
        }
    }

    /**
     * Refund a given amount of Euros.
     *
     * @param float $credit
     * @param array|null $keys
     */
    public function refundUntil(float $credit, ?array $keys = null): void
    {
        if ($credit > 0) {
            Log::info('Refund ' . number_format($credit, 2) . ' to ' . $this->email);
            $credit = (int) ceil($credit * 100);

            foreach ($this->getSubscriptions($keys) as $subscription) {
                if ($subscription->active()) {
                    $payment = $subscription->latestPayment();
                    $refund = (int) min($credit, $payment->rawAmount());
                    $this->refund(
                        $payment->asStripePaymentIntent()['id'],
                        ['amount' => $refund],
                    );
                    $credit -= $refund;

                    if ($credit <= 0) {
                        return;
                    }
                }
            }
        }
    }

    public function hasVerifiedProperties(): bool
    {
        return $this->apiAuthorizations->whereNotNull('verified_at')->count() > 0;
    }

    public function canSubscribeAPlan(): bool
    {
        return $this->hasStripeId() || $this->hasVerifiedProperties();
    }

    public function getActiveSubscription(): ?Subscription
    {
        if (!$this->activeSubscriptionCached) {
            $this->activeSubscription = $this->hasStripeId()
                ? collect(iterator_to_array($this->getCustomerSubscriptions()))
                    ->where('status', 'active')->last()
                : null;
            $this->activeSubscriptionCached = true;
        }

        return $this->activeSubscription;
    }

    public function clearActiveSubscriptionCache(): void
    {
        $this->activeSubscriptionCached = false;
    }

    /**
     * Returns the age in months (rounded down) of the current active subscription
     * or null if the user currently has no active subscriptions.
     *
     * @return int|null
     */
    public function getCurrentActiveSubscriptionAge(): ?int
    {
        $subscription = $this->getActiveSubscription();

        if (!$subscription) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($subscription['created'])->diffInMonths();
    }

    public function getPlanId(?array $planKeys = null): ?string
    {
        return collect($planKeys ?? array_keys(Plan::getPlansData()))->first(
            fn(string $planId) => $this->subscribed($planId),
        );
    }

    public function getPaidPlan(?array $planKeys = null): ?string
    {
        return collect($planKeys ?? array_keys(Plan::getPlansData()))->first(
            fn(string $planId) => $this->subscribed($planId),
        );
    }

    public function getPlan(?array $planKeys = null): Plan
    {
        return  Plan::fromId($this->getPaidPlan($planKeys) ?? 'free');
    }

    public function getPaidRequests(?int $month = null): ?int
    {
        $subscriptionAge = $month ?? $this->getCurrentActiveSubscriptionAge();

        if ($subscriptionAge === null) {
            return null;
        }

        $id = $this->subscriptions->where('stripe_status', 'active')->first()?->id;

        if (!$id) {
            $userId = $this->id;
            Log::warning("No subscription id found while age = $subscriptionAge for user $userId");

            return 0;
        }

        $subscriptionBaseDirectory = __DIR__ . '/../../data/subscription-count/';
        $subscriptionDirectory = $subscriptionBaseDirectory . 's' . $id;
        $subscriptionFile = $subscriptionDirectory . '/m' . $subscriptionAge . '.txt';

        return (int) @file_get_contents($subscriptionFile);
    }

    public function getPlanRatio(?string $planId = null, ?Closure $getter = null): float
    {
        $planId = $planId ?? $this->getPlanId();
        $planData = Plan::getPlansData();
        $plan = $planData[$planId] ?? null;

        if ($planId) {
            return $this->getPaidRequests() / $plan['limit'];
        }

        return $this->apiAuthorizations->reduce(
            static fn (int $max, ApiAuthorization $authorization) => max(
                $max,
                (int) ($getter ? $getter($authorization) : $authorization->getFreeCount()),
            ),
            0,
        ) / Plan::fromId('free')['limit'];
    }

    public function getUnverifiedPlanRatio(?string $planId = null)
    {
        return $this->getPlanRatio(
            $planId,
            static fn (ApiAuthorization $authorization) => $authorization->getUnverifiedCount(),
        );
    }

    public function getLimit(array|Plan|string|int|float|null $plan = null): int|float
    {
        if (is_string($plan)) {
            $plan = Plan::fromId($plan);
        }

        if (is_array($plan) && !isset($plan['limit'])) {
            $plan = $this->getPlan($plan);
        }

        $limit = (float) (is_array($plan) || $plan instanceof ArrayAccess ? ($plan['limit'] ?? 0) : $plan);
        $limit *= (float) (config('app.quota_factor')[$this->id] ?? 1);
        $intLimit = (int) $limit;

        return $limit === (float) $intLimit ? $intLimit : $limit;
    }

    public function getCardIcon(): string
    {
        return asset(
            'img/' .
            preg_replace('/[^a-z]/', '', strtolower($this->card_brand ?? 'unknown')) .
            '.png',
        );
    }

    public function isSuperAdmin(): bool
    {
        return $this->email === config('app.super_admin');
    }

    public function subscribe(string $planId, string $planOffer, ?string $paymentMethod = null): CashierSubscription
    {
        return $this->newSubscription($planId, $planOffer)->create(
            $paymentMethod ?? $this->defaultPaymentMethod()?->id,
            ['email' => $this->email],
        );
    }

    public function subscribePlan(string $planId, string $recurrence, ?string $paymentMethod = null): CashierSubscription
    {
        return $this->subscribe($planId, config("plan.$planId.price.$recurrence"), $paymentMethod);
    }

    public function hasBilling(): bool
    {
        return $this->last_subscribe_at && $this->hasStripeId();
    }
}
