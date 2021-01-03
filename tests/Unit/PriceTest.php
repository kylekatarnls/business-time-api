<?php

namespace Tests\Unit;

use Tests\TestCase;

final class PriceTest extends TestCase
{
    public function testPriceHelper(): void
    {
        $app = $this->createApplication();
        $locale = $app->getLocale();
        $this->assertSame('fr', $locale);
        $this->assertSame('1235 €', price(1235, 'EUR'));
        $app->setLocale('en_US');
        $this->assertSame('€1235', price(1235, 'EUR'));
        $app->setLocale('fr');
        $this->assertSame('1234,57 €', price('1234,57', 'EUR'));
        $app->setLocale('en_US');
        $this->assertSame("€1234.57", price('1234.57', 'EUR'));
        $app->setLocale($locale);
    }
}
