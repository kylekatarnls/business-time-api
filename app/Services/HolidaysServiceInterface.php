<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HolidayInterface;

interface HolidaysServiceInterface
{
    /** @return list<HolidayInterface> */
    public function getYearHolidays(string $language, string $region, int $year, array $options): array;
}
