<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use ReflectionMethod;
use Tests\TestCase;

final class ControllerTest extends TestCase
{
    public function testGetGuestPlan(): void
    {
        $controller = new Controller();
        $getGuestPlan = new ReflectionMethod(Controller::class, 'getGuestPlan');
        $getGuestPlan->setAccessible(true);
        /** @var Plan $plan */
        $plan = $getGuestPlan->invoke($controller);

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertSame([
            'title'    => 'Guest',
            'name'     => 'Vicopo Guest',
            'price'    => 0,
            'limit'    => 1_000,
            'product'  => 'guest',
            'currency' => 'eur',
        ], $plan->getArrayCopy());
    }

    public function testGetFreePlan(): void
    {
        $controller = new Controller();
        $getFreePlan = new ReflectionMethod(Controller::class, 'getFreePlan');
        $getFreePlan->setAccessible(true);
        /** @var Plan $plan */
        $plan = $getFreePlan->invoke($controller);

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertSame([
            'title'    => 'Free',
            'name'     => 'Vicopo Free',
            'price'    => 0,
            'limit'    => 5_000,
            'product'  => 'free',
            'currency' => 'eur',
        ], $plan->getArrayCopy());
    }
}
