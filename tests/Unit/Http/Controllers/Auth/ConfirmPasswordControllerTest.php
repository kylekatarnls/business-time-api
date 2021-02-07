<?php

namespace Tests\Unit\Http\Controllers\Auth;

use App\Http\Controllers\Auth\ConfirmPasswordController;
use Tests\TestCase;

final class ConfirmPasswordControllerTest extends TestCase
{
    public function testConstruct(): void
    {
        $controller = new ConfirmPasswordController();

        $this->assertInstanceOf(ConfirmPasswordController::class, $controller);
        $this->assertSame(
            ['auth'],
            collect($controller->getMiddleware())
                ->map(static fn (array $middleware) => $middleware['middleware'])
                ->toArray(),
        );
    }
}
