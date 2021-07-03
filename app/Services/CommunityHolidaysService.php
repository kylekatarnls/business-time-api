<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Holiday;
use App\Models\HolidayInterface;
use Carbon\CarbonImmutable;
use Cmixin\BusinessTime;
use Illuminate\Support\Facades\Date;

final class CommunityHolidaysService implements HolidaysServiceInterface
{
    /** @return list<HolidayInterface> */
    public function getYearHolidays(string $language, string $region, int $year, array $options): array
    {
        Date::setLocale($language);
        $region = preg_replace('/^usa(?![A-Za-z])/', 'us', $region);
        BusinessTime::enable(CarbonImmutable::class, [
            'holidays' => [
                'region' => $region,
            ],
        ]);
        $dates = Date::getYearHolidays($year);
        $names = Date::getHolidayNamesDictionary($language);

        return array_map(
            fn (string $id) => $this->createHoliday($id, $dates, $names),
            array_keys($dates),
        );
    }

    private function createHoliday(string $id, array $dates, array $names): Holiday
    {
        $data = Date::getHolidayDataById($id);
        $start = Date::parse($dates[$id]);

        return new Holiday([
            'calendar_id' => $id,
            'name' => $names[$id] ?? null,
            'start' => preg_replace('/ 00:00:00$/', '', $start->format('Y-m-d H:i:s')),
            'end' => $start->addDay()->format('Y-m-d'),
            'data' => empty($data) ? null : $data,
        ]);
    }
}
