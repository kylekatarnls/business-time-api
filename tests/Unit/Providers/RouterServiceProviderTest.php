<?php

namespace Tests\Unit\Providers;

use App\Providers\RouteServiceProvider;
use App\Util\CommerceBalance;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RouterServiceProviderTest extends TestCase
{
    public function testRateLimit(): void
    {
        $ip = '23.45.67.89';
        $request = new Request();
        $request->server->set('REMOTE_ADDR', $ip);
        /** @var Limit $limit */
        $limit = RateLimiter::limiter('api')($request);

        $this->assertSame($ip, $limit->key);
        $this->assertSame(60, $limit->maxAttempts);
        $this->assertSame(1, $limit->decayMinutes);
    }

    public function testBoot(): void
    {
        $provider = new RouteServiceProvider($this->app);
        $provider->boot();
        /** @var Router $router */
        $router = Route::getFacadeRoot();

        $this->assertTrue($router->hasMiddlewareGroup('api'));
        $this->assertTrue($router->hasMiddlewareGroup('web'));
    }
}
