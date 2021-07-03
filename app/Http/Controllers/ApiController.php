<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Services\CommunityHolidaysService;
use App\Services\GcalHolidaysService;
use App\Services\HolidaysServiceInterface;
use App\Services\ICSGeneratorService;
use App\Services\OfficialHolidaysService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

final class ApiController extends AbstractController
{
    public function events(string $type, string $language, string $region, int $year, Request $request): Response|array
    {
        $apiKey = $request->get('apiKey');

        if (!$apiKey) {
            throw new InvalidArgumentException('Missing apiKey');
        }

        if (!ApiKey::where(['key' => $apiKey])->exists()) {
            throw new InvalidArgumentException('Invalid apiKey');
        }

        $service = $this->getHolidaysService($type);
        $holidays = $service->getYearHolidays($language, $region, $year, [
            'full' => (bool) (int) $request->get('full'),
        ]);

        switch ($request->get('format')) {
            case 'ics':
                return new Response(
                    app(ICSGeneratorService::class)->generate($region, $year, $holidays),
                    200,
                    ['Content-Type' => 'text/calendar'],
                );
        }

        return compact('type', 'language', 'region', 'year', 'holidays');
    }

    private function getHolidaysService(string $type): HolidaysServiceInterface
    {
        return match ($type) {
            'community' => app(CommunityHolidaysService::class),
            'gcal' => app(GcalHolidaysService::class),
            'official' => app(OfficialHolidaysService::class),
            default => throw new InvalidArgumentException("$type type unsupported"),
        };
    }
}
