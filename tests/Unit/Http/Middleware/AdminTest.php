<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\Admin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class AdminTest extends TestCase
{
    public function testInsufficientPermissions(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Insufficient permissions.');

        $admin = new Admin();
        Auth::login($this->newZiggy());

        $admin->handle(new Request(), static function () {});
    }

    public function testHandle(): void
    {
        $admin = new Admin();
        $ziggy = $this->newZiggy();
        $ziggy->email = config('app.super_admin');
        Auth::login($ziggy);
        $passedRequest = null;
        $newRequest = new Request();

        $admin->handle($newRequest, static function (Request $request) use (&$passedRequest)
        {
            $passedRequest = $request;
        });

        $this->assertSame($passedRequest, $newRequest);
    }
}
