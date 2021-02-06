<?php

namespace Tests\Unit\Model;

use App\Models\SubscriptionQuotaNotification;
use Laravel\Cashier\Subscription;
use Tests\TestCase;

final class SubscriptionQuotaNotificationTest extends TestCase
{
    public function testRelations(): void
    {
        $ziggy = $this->newZiggy();
        /** @var Subscription $subscription */
        $subscription = $ziggy->subscriptions()->create([
            'name' => 'start',
            'stripe_id' => 1,
            'stripe_status' => 'active',
        ]);
        /** @var SubscriptionQuotaNotification $notification */
        $notification = $ziggy->subscriptionQuotaNotifications()->create([
            'year' => 0,
            'month' => 1,
            'percentage' => 80,
            'subscription_id' => $subscription->id,
        ]);

        $this->assertSame($ziggy->id, $notification->user->id);
        $this->assertSame($subscription->id, $notification->subscription->id);
    }
}
