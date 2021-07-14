<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Holiday;
use App\Models\HolidayInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;

final class OfficialHolidaysService implements HolidaysServiceInterface
{
    public function __construct(private DownloadService $downloadService)
    {
    }

    /** @return list<HolidayInterface> */
    public function getYearHolidays(string $language, string $region, int $year, array $options): array
    {
        $calendar = "$language.$region";
        $key = "off:$year-$calendar";
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
        $url = strtr(config('calendar.office_url'), ['{{region}}' => strtr($region, ['-' => '/'])]);
        $calendar = Reader::read(preg_replace(
            '/(:\d{8}T)\d{1,5}Z/',
            '${1}000000Z',
            $this->downloadService->download($url),
        ));
        $events = [];

        foreach ($calendar->VEVENT as $event) {
            $holiday = $this->createHoliday($event, $full);

            if ($this->isHolidayBetween($holiday, $startOfYear, $endOfYear)) {
                $events[] = $holiday;
            }
        }

        Cache::forever($key, $events);

        return $events;
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

    private function createHoliday(VEvent $event, bool $full): ?Holiday
    {
        $start = $this->formatDate($event?->DTSTART?->getValue());
        $end = $this->formatDate($event?->DTEND?->getValue());
        $id = $event?->UID?->getValue();
        $summary = $event?->SUMMARY?->getValue();

        return $start && $id && $summary ? new Holiday([
            'calendar_id' => $id,
            'name' => $summary,
            'start' => $start,
            'end' => $end ?? Date::parse($start)->addDay()->format('Y-m-d'),
            'data' => $full ? $event->jsonSerialize() : null,
        ]) : null;
    }

    private function formatDate(?string $date): ?string
    {
        return $date ? preg_replace('/^(\d+)(\d{2})(\d{2})$/', '$1-$2-$3', $date) : null;
    }
}
