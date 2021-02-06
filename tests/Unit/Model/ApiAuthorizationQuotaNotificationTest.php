<?php

namespace Tests\Unit\Model;

use App\Models\ApiAuthorization;
use App\Models\ApiAuthorizationQuotaNotification;
use Tests\TestCase;

final class ApiAuthorizationQuotaNotificationTest extends TestCase
{
    public function testRelations(): void
    {
        $ziggy = $this->newZiggy();
        /** @var ApiAuthorization $authorization */
        $authorization = $ziggy->apiAuthorizations()->create([
            'name' => 'Music',
            'type' => 'domain',
            'value' => 'music.github.io',
        ]);
        /** @var ApiAuthorizationQuotaNotification $notification */
        $notification = $ziggy->apiAuthorizationQuotaNotifications()->create([
            'year' => 2012,
            'month' => 11,
            'percentage' => 100,
            'api_authorization_id' => $authorization->id,
        ]);

        $this->assertSame($ziggy->id, $notification->user->id);
        $this->assertSame($authorization->id, $notification->apiAuthorization->id);
    }
}
