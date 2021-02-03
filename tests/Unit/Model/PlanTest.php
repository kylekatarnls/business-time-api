<?php

namespace Tests\Unit\Model;

use App\Models\Plan;
use Tests\TestCase;

final class PlanTest extends TestCase
{
    public function testPlan(): void
    {
        $plan = Plan::fromId('start');
        $this->assertSame(9.9, $plan->priceAmount());
        $this->assertSame(2.0 * 990, $plan->priceAmount(2));
        $this->assertSame('9,90', $plan->price());
        $this->assertSame('990,00', $plan->price(1));
        $this->assertSame([
            'title' => 'Start',
            'name' => 'Vicopo Start',
            'price' => 990,
            'limit' => 20000,
            'product' => config("plan.start.id"),
            'currency' => 'eur',
            'extra' => 'foo',
        ], $plan->with(['extra' => 'foo']));
    }
}
