<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class ApiController extends AbstractController
{
    public function events(string $region, int $year, Request $request): array
    {
        $apiKey = $request->get('apiKey');

        if (!$apiKey) {
            throw new InvalidArgumentException('Missing apiKey');
        }

        if (!ApiKey::findByKey($apiKey)) {
            throw new InvalidArgumentException('Invalid apiKey');
        }

        $key = "$year-$region";
        $cachedResult = Cache::get($key);

        if ($cachedResult) {
            return ['cached' => true, 'events' => $cachedResult];
        }

        $startOfYear = CarbonImmutable::parse("$year-01-01");
        $url = strtr(config('calendar.google_calendar_api_url'), ['{{region}}' => 'en.usa']) .
            '?' . http_build_query([
                'key' => config('calendar.google_calendar_api_key'),
                'timeMin' => $startOfYear->subDays(2)->toISOString(),
                'timeMax' => $startOfYear->endOfYear()->addDays(2)->toISOString(),
            ]);
        $result = json_decode(file_get_contents($url));
        Cache::forever($key, $result);

        return ['cached' => false, 'events' => $result];
    }
}
