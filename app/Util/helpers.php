<?php

declare(strict_types=1);

require_once __DIR__ . '/utilHelpers.php';

if (!function_exists('price')) {
    function price(string $amount, ?string $currency = null): string
    {
        return \App\Util\price($amount, $currency);
    }
}
