<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\TrustHosts;
use Tests\TestCase;

final class TrustedHostsTest extends TestCase
{
    public function testHosts(): void
    {
        $this->assertSame(['^(.+\.)?127\.0\.0\.1$'], (new TrustHosts($this->app))->hosts());
    }
}
