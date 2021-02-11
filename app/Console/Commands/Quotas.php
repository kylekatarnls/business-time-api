<?php

namespace App\Console\Commands;

use App\Mail\LimitThreshold;
use App\Models\ApiAuthorization;
use App\Models\ApiAuthorizationQuotaNotification;
use App\Models\Plan;
use App\Models\SubscriptionQuotaNotification;
use App\Models\User;
use App\Util\SendMail;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;

final class Quotas extends Command
{
    use SendMail;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quotas {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send e-mail on limit threshold exceeded.';

    private CarbonImmutable $now;
    private int $year;
    private int $month;

    public function __construct(
        /** @var array<int, int> */
        private ?array $apiAuthorizationThresholds = null,
        /** @var array<int, int> */
        private ?array $subscriptionThreshold = null,
        /** @var Closure(): User[] */
        private ?Closure $usersGetter = null
    ) {
        $this->apiAuthorizationThresholds = $this->apiAuthorizationThresholds
            ?? config('notification.quota.api_authorization');

        $this->subscriptionThreshold = $this->subscriptionThreshold
            ?? config('notification.quota.subscription');

        $now = CarbonImmutable::now();
        $nextMonth = $now->copy()->startOfMonth()->addMonth();

        if ($nextMonth->diffInMinutes($now) < 3) {
            $now = $nextMonth;
        }

        $this->now = $now;
        $this->year = $now->year;
        $this->month = $now->month;

        parent::__construct();
    }

    public function handle(): int
    {
        foreach (($this->usersGetter ?? static fn () => User::all())() as $user) {
            $this->checkQuotasFor($user);
        }

        return 0;
    }

    private function checkQuotasFor(User $user): void
    {
        $authorizations = $user->apiAuthorizations()->whereNotNull('verified_at')->get();

        if ($authorizations->count()) {
            $this->checkProperties($user, $authorizations);
        }
    }

    private function number($number): string
    {
        return number_format((float) $number, thousands_separator: ' ');
    }

    private function percent(float $ratio): string
    {
        return number_format($ratio * 100, decimal_separator: ',') . '%';
    }

    private function fraction($numerator, $denominator, $pad = 16): string
    {
        return str_pad($this->number($numerator) . ' / ' . $this->number($denominator), $pad, pad_type: STR_PAD_LEFT) .
            str_pad($this->percent($numerator / $denominator), 6, pad_type: STR_PAD_LEFT);
    }

    /**
     * @param User $user
     * @param Collection<ApiAuthorization> $authorizations
     * @psalm-param ApiAuthorization[] $authorizations
     */
    private function checkProperties(User $user, Collection $authorizations): void
    {
        $planId = $user->getPlanId() ?? 'free';
        $plan = Plan::fromId($planId);
        $limit = $plan['limit'];

        $this->writeLine("$planId: " . $user->name);
        $this->writeLine($user->id . '   ' . $user->email);

        if ($planId === 'free') {
            $this->checkAuthorizations($user, $authorizations, $limit);

            return;
        }

        if ($limit < INF) {
            $this->checkSubscription($user, $plan);
        }
    }

    private function checkAuthorizations(User $user, Collection $authorizations, int $limit): void
    {
        $maxQuotas = [];
        /** @var ApiAuthorizationQuotaNotification[] $reachedQuotas */
        $reachedQuotas = $user
            ->apiAuthorizationQuotaNotifications()
            ->where([
                'year' => $this->year,
                'month' => $this->month,
            ])
            ->get();

        foreach ($reachedQuotas as $quota) {
            $maxQuotas[$quota->api_authorization_id] = max(
                $maxQuotas[$quota->api_authorization_id] ?? 0,
                $quota->percentage,
            );
        }

        foreach ($authorizations as $authorization) {
            $freeCount = $authorization->getFreeCount();
            $percentage = $freeCount * 100 / $limit;
            $nextSteps = array_filter(
                $this->apiAuthorizationThresholds,
                static fn (int $threshold) => $threshold > ($maxQuotas[$authorization->id] ?? 0)
                    && $percentage >= $threshold,
            );

            if (count($nextSteps) && !$this->isAuthorizationPaid($authorization->type, $authorization->value)) {
                $threshold = max($nextSteps);

                $this->writeLine(
                    ' - ' . str_pad($authorization->value, 36) .
                    $this->fraction($freeCount, $limit) .
                    '  >=  ' . $threshold . '%',
                );

                $interpolations = [
                    'property' => $authorization->value,
                    'percent' => $threshold,
                ];
                $this->sendLimitMail(
                    $user,
                    $threshold >= 100
                        ? __('[Warning] Quota for :property exceeded :percent%', $interpolations)
                        : __('Quota for :property exceeded :percent%', $interpolations),
                    $threshold >= 100
                        ? __('The property :property will now be blocked until the end of the month, you can unblock it using the link below:', $interpolations)
                        : __('The property :property will be blocked soon, you can raise your monthly limit using the link below:', $interpolations),
                    [
                        'authorization' => $authorization->id,
                        'count' => $freeCount,
                        'limit' => $limit,
                    ],
                );

                $this->recordNotification($user->apiAuthorizationQuotaNotifications(), [
                    'api_authorization_id' => $authorization->id,
                    'year' => $this->year,
                    'month' => $this->month,
                    'percentage' => $threshold,
                ]);
            }
        }

        $this->writeLine();
    }

    private function isAuthorizationPaid(string $type, string $value): bool
    {
        $userIds = ApiAuthorization::where(['type' => $type, 'value' => $value])
            ->distinct('user_id')
            ->get('user_id')
            ->map(static fn (ApiAuthorization $auth) => $auth->user_id);

        return Subscription::whereIn('user_id', $userIds)
            ->where('stripe_status', 'active')
            ->count() > 0;
    }

    private function checkSubscription(User $user, Plan $plan): void
    {
        $limit = $user->getLimit($plan);
        $subscription = $user->getActiveSubscription();
        $id = $user->subscriptions()->firstWhere('stripe_id', $subscription->id)->id;
        $months = $user->getCurrentActiveSubscriptionAge();
        $end = CarbonImmutable::createFromTimestamp($subscription->current_period_end)->isoFormat('LLLL');
        $paidRequests = $user->getPaidRequests();

        $this->writeLine(
            json_encode($months) . ' months  ' .
            $this->fraction($paidRequests, $limit, 20),
        );

        $year = floor($months / CarbonInterface::MONTHS_PER_YEAR);
        $month = $months % CarbonInterface::MONTHS_PER_YEAR;

        $maxQuotas = [];
        /** @var SubscriptionQuotaNotification[] $reachedQuotas */
        $reachedQuotas = $user
            ->subscriptionQuotaNotifications()
            ->where([
                'subscription_id' => $id,
                'year' => $year,
                'month' => $month,
            ])
            ->get();

        foreach ($reachedQuotas as $quota) {
            $maxQuotas[$quota->subscription_id] = max(
                $maxQuotas[$quota->subscription_id] ?? 0,
                $quota->percentage,
            );
        }

        $percentage = $paidRequests * 100 / $limit;
        $nextSteps = array_filter(
            $this->apiAuthorizationThresholds,
            static fn (int $threshold) => $threshold > ($maxQuotas[$id] ?? 0)
                && $percentage >= $threshold,
        );

        if (count($nextSteps)) {
            $threshold = max($nextSteps);

            $this->writeLine(
                ' - ' . str_pad($plan['title'], 36) .
                $this->fraction($paidRequests, $limit) .
                '  >=  ' . $threshold . '%',
            );

            $interpolations = [
                'property' => $plan['name'],
                'percent' => $threshold,
                'end' => $end,
            ];
            $this->sendLimitMail(
                $user,
                $threshold >= 100
                    ? __('[Warning] Quota for :property exceeded :percent%', $interpolations)
                    : __('Quota for :property exceeded :percent%', $interpolations),
                $threshold >= 100
                    ? __('Your :property subscription will now be blocked until :end, you can unblock it using the link below:', $interpolations)
                    : __('Your :property subscription reached :percent%, if you think it may reach 100% before :end, you can raise the limit using the link below:', $interpolations),
                [
                    'subscription' => $id,
                    'count' => $paidRequests,
                    'limit' => $limit,
                ],
            );

            $this->recordNotification($user->subscriptionQuotaNotifications(), [
                'subscription_id' => $id,
                'year' => $year,
                'month' => $month,
                'percentage' => $threshold,
            ]);
        }

        $this->writeLine();
    }

    private function recordNotification(HasMany $notificationList, array $attributes)
    {
        if ($this->option('dry-run')) {
            return;
        }

        $notificationList->create($attributes);
    }

    private function sendLimitMail(User $user, string $title, string $content, array $properties = []): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        Log::info('Notice to ' . $user->email . "\n" . $title . "\n" . $content);

        $this->sendMailSilently(
            $user->email,
            new LimitThreshold([
                'title' => $title,
                'link' => route('plan'),
                'content' => $content,
            ]),
            [
                'properties' => array_merge([
                    'user' => $user->id,
                    'email' => $user->email,
                ], $properties),
            ],
        );
    }

    private function writeLine(string $line = ''): void
    {
        if (!$this->option('verbose')) {
            return;
        }

        $this->getOutput()->writeln($line);
    }
}
