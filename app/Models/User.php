<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription as CashierSubscription;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Stripe\Subscription;
use Throwable;

/**
 * @property string $email
 * @property string $name
 * @property Collection<CashierSubscription> $subscriptions
 * @property Collection<ApiAuthorization> apiAuthorizations
 */
final class User extends Authenticatable
{
    use Billable;
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        /** @var Subscription[] $subscriptions */
        $subscriptions = $this->asStripeCustomer()->subscriptions;

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

    public function refundUntil(int $credit, ?array $keys = null): void
    {
        if ($credit > 0) {
            Log::info('Refund ' . number_format($credit / 100, 2) . ' to ' . $this->email);

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

    public function getActiveSubscription(): ?Subscription
    {
        if (!$this->activeSubscriptionCached) {
            $this->activeSubscription = $this->hasStripeId()
                ? collect(iterator_to_array($this->asStripeCustomer()->subscriptions))
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

    public function getPaidRequests(?int $month = null): ?int
    {
        $subscriptionAge = $month ?? $this->getCurrentActiveSubscriptionAge();

        if ($subscriptionAge === null) {
            return null;
        }

        $subscriptionBaseDirectory = __DIR__ . '/../../data/subscription-count/';
        $subscriptionDirectory = $subscriptionBaseDirectory . 'u' . $this->id;
        $subscriptionFile = $subscriptionDirectory . '/m' . $subscriptionAge . '.txt';

        return (int) @file_get_contents($subscriptionFile);
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
}
