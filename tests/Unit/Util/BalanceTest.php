<?php

namespace Tests\Unit\Util;

use App\Util\CommerceBalance;
use Tests\TestCase;

final class BalanceTest extends TestCase
{
    public function testBalance(): void
    {
        $balance = CommerceBalance::get()->toArray();
        $amount = $balance['available'][0]['amount'] ?? null;

        $this->assertIsInt($amount);
    }
}
