<?php

namespace Tests\Unit\Model;

use App\Models\Plan;
use Tests\TestCase;

final class UserTest extends TestCase
{
    public function testGetLimit(): void
    {
        $factor = $this->app['config']->get('app.quota_factor');
        $ziggy = $this->newZiggy();

        $this->app['config']->set('app.quota_factor', []);

        $this->assertSame(0, $ziggy->getLimit());
        $this->assertSame(0, $ziggy->getLimit(null));
        $this->assertSame(200, $ziggy->getLimit(200));

        $this->app['config']->set('app.quota_factor', [
            $ziggy->id => 3,
        ]);

        $this->assertSame(600, $ziggy->getLimit(200));
        $this->assertSame(600_000, $ziggy->getLimit('pro'));
        $this->assertSame(INF, $ziggy->getLimit('premium'));

        $this->app['config']->set('app.quota_factor', $factor);

        $this->assertSame(20_000, $ziggy->getLimit(Plan::fromId('start')));
        $this->assertSame(5_000, $ziggy->getLimit(['start', 'pro']));
    }
}
