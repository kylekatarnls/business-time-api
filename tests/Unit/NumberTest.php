<?php

namespace Tests\Unit;

use App\Util\Number;
use Tests\TestCase;

final class NumberTest extends TestCase
{
    public function testFormat(): void
    {
        $app = $this->createApplication();
        $locale = $app->getLocale();
        $this->assertSame('fr', $locale);
        $this->assertSame('1 235', Number::format(1234.567));
        $app->setLocale('en_US');
        $this->assertSame("1,235", Number::format(1234.567));
        $app->setLocale('fr');
        $this->assertSame('1 234,57', Number::format(1234.567, 2));
        $app->setLocale('en_US');
        $this->assertSame("1,234.57", Number::format(1234.567, 2));
        $app->setLocale($locale);
    }
}
