<?php

namespace App\Console;

use App\Models\ApiAuthorization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class QuotasCommand
{
    public function run(): void
    {
        foreach (User::all() as $user) {
            $this->checkQuotasFor($user);
        }
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

        echo "$planId: " . $user->name . "\n";
        echo $user->id . '   ' . $user->email . "\n";

        if ($planId === 'free') {
            foreach ($authorizations as $authorization) {
                echo ' - ' . str_pad($authorization->value, 36) .
                    $this->fraction($authorization->getFreeCount(), $plan['limit']) .
                    "\n";
            }

            echo "\n";

            return;
        }

        echo json_encode($user->getCurrentActiveSubscriptionAge()) . ' months  ' .
            $this->fraction($user->getPaidRequests(), $plan['limit'], 20) . "\n";

        foreach ($authorizations as $authorization) {
            echo ' - ' . str_pad($authorization->value, 36) .
                $this->number($authorization->getFreeCount()) .
                "\n";
        }

        echo "\n";
    }
}
