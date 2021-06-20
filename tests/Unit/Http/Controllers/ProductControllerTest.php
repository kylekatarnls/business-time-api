<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\ProductController;
use Carbon\Carbonite\Attribute\Freeze;
use Tests\TestCase;

final class ProductControllerTest extends TestCase
{
    #[Freeze('2021-02-21 19:26:37.350912')]
    public function testWebhooks(): void
    {
        $controller = new ProductController();
        $file = __DIR__ . '/../../../../storage/stripe/hook-2021-02-21--19-26-37--350912.json';
        @unlink($file);
        $controller->webhooks();

        $this->assertFileExists($file);

        @unlink($file);
    }
}
