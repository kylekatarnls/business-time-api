<?php

namespace Tests\Unit\Http\Controllers\Auth;

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Arr;
use Tests\TestCase;

final class LoginControllerTest extends TestCase
{
    public function testConstruct(): void
    {
        $controller = new LoginController();

        $this->assertInstanceOf(LoginController::class, $controller);
        $this->assertSame(
            [['middleware' => 'guest', 'options' => ['except' => ['logout']]]],
            collect($controller->getMiddleware())
                ->map(static fn (array $middleware) => Arr::only($middleware, ['middleware', 'options']))
                ->toArray(),
        );
    }
}
