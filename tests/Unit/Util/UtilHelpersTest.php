<?php

namespace Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use function App\Util\integerOrInfinity;

final class UtilHelpersTest extends TestCase
{
    public function testIntegerOrInfinity(): void
    {
        $this->assertSame(0, integerOrInfinity(null));
        $this->assertSame(0, integerOrInfinity(0));
        $this->assertSame(INF, integerOrInfinity(INF));
        $this->assertSame(INF, integerOrInfinity('Inf'));
        $this->assertSame(INF, integerOrInfinity('Infinity'));
        $this->assertSame(1, integerOrInfinity(1.23));
        $this->assertSame(3, integerOrInfinity(3.999));
    }
}
