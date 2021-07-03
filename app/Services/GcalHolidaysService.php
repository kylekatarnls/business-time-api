<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Holiday;
use App\Models\HolidayInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

final class GcalHolidaysService implements HolidaysServiceInterface
{
    public function __construct(private DownloadService $downloadService)
    {
    }

    /** @return list<HolidayInterface> */
    public function getYearHolidays(string $language, string $region, int $year, array $options): array
    {
        $calendar = "$language.$region";
        $key = "$year-$calendar";
        $full = $options['full'] ?? false;

        if ($full) {
            $key .= ':full';
        }

        $cachedResult = Cache::get($key);

        if ($cachedResult) {
            return $cachedResult;
        }

        $startOfYear = Date::parse("$year-01-01");
        $endOfYear = $startOfYear->endOfYear();
        $url = strtr(config('calendar.google_calendar_api_url'), ['{{calendar}}' => $calendar]) .
            '?' . http_build_query([
                'key' => config('calendar.google_calendar_api_key'),
                'timeMin' => $startOfYear->subDays(2)->toISOString(),
                'timeMax' => $endOfYear->addDays(2)->toISOString(),
            ]);
        $result = array_filter(
            array_map(
                fn (array $item) => $this->createHoliday($item, $full),
                json_decode($this->downloadService->download($url), true)['items'] ?? [],
            ),
            fn (?Holiday $holiday) => $this->isHolidayBetween($holiday, $startOfYear, $endOfYear),
        );
        usort($result, static fn (Holiday $a, Holiday $b) => $a->start <=> $b->start);
        Cache::forever($key, $result);

        return $result;
    }

    private function isHolidayBetween(
        ?Holiday $holiday,
        DateTimeInterface $startOfYear,
        DateTimeInterface $endOfYear
    ): bool {
        return $holiday
            && Date::parse($holiday->start) <= $endOfYear
            && Date::parse($holiday->end) > $startOfYear;
    }

    private function createHoliday(array $item, bool $full): ?Holiday
    {
        $start = $item['start']['date'] ?? null;
        $end = $item['end']['date'] ?? null;
        $id = $item['id'] ?? null;
        $summary = $item['summary'] ?? null;

        return $start && $id && $summary ? new Holiday([
            'calendar_id' => $id,
            'name' => $summary,
            'start' => $start,
            'end' => $end ?? Date::parse($start)->addDay()->format('Y-m-d'),
            'data' => $full ? $item : null,
        ]) : null;
    }
}
