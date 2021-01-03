<?php

declare(strict_types=1);

namespace App\Util;

/**
 * @param string|int|float|null $value
 *
 * @return int|float
 */
function integerOrInfinity($value)
{
    if ($value === INF || is_string($value) && preg_match('/^Inf(?:inity)?$/', $value)) {
        return INF;
    }

    return (int) $value;
}

function price(string $amount, ?string $currency = null): string
{
    $currency = $currency ?? config('app.default_currency');
    $currency = [
        'eur' => '€',
        'usd' => '$',
    ][strtolower($currency)] ?? $currency;

    return __('number.price', [
        'currency' => $currency,
        'amount' => $amount,
    ]);
}
