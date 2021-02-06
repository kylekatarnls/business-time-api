<?php

namespace Tests\Unit\Http\Controllers\Auth;

use App\Http\Controllers\Auth\VerificationController;
use Tests\TestCase;

final class VerificationControllerTest extends TestCase
{
    public function testConstruct(): void
    {
        $controller = new VerificationController();

        $this->assertInstanceOf(VerificationController::class, $controller);
        $this->assertSame(
            ['auth', 'signed', 'throttle:6,1'],
            collect($controller->getMiddleware())
                ->map(static fn (array $middleware) => $middleware['middleware'])
                ->toArray(),
        );
    }
}
