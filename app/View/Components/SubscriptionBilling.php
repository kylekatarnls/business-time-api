<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;
use Livewire\Component;

class SubscriptionBilling extends Component
{
    public bool $confirmingSubscriptionCancellation = false;

    public array $viewData;

    public function mount(string $date, string $dateTime, ?string $subscription, ?string $end): void
    {
        $this->viewData = compact('date', 'dateTime', 'subscription', 'end');
    }

    public function confirmSubscriptionCancellation(): void
    {
        $this->confirmingSubscriptionCancellation = true;
    }

    public function cancelSubscription(): void
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Subscription $subscription */
        $subscription = $user->subscriptions->where('stripe_id', $this->viewData['subscription'])->first();
        $subscription->cancel();

        Log::warning('User ' . $user->id . ' cancelled subscription ' . $subscription->ends_at);

        $this->redirect(route('dashboard'));
    }

    public function render(): View
    {
        return view('partials.subscription-billing', $this->viewData);
    }
}
