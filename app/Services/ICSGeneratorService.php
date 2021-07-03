<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HolidayInterface;
use DateTimeImmutable;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

final class ICSGeneratorService
{
    /** @param list<HolidayInterface> $holidays */
    public function generate(string $region, int $year, array $holidays): string
    {
        $calendar = Calendar::create("$region $year");

        foreach ($holidays as $holiday) {
            $calendar->event(Event::create($holiday->getName())
                ->startsAt(new DateTimeImmutable($holiday->getStart()))
                ->endsAt(new DateTimeImmutable($holiday->getEnd()))
                ->uniqueIdentifier($holiday->getCalendarId())
            );
        }

        return $calendar->get();
    }
}
