<?php

declare(strict_types=1);

namespace App\Models;

interface HolidayInterface
{
    public function getCalendarId(): string;

    public function getName(): ?string;

    public function getStart(): string;

    public function getEnd(): string;

    public function getData(): ?array;
}
