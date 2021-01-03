<?php

declare(strict_types=1);

namespace App\Util;

final class Number
{
    public static function format($number, int $precision = 0): string
    {
        return number_format($number ?? 0, $precision, __('number.decimal'), __('number.thousand'));
    }
}
