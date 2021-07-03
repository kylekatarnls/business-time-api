<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

final class DownloadService
{
    public function download(string $url, DateTimeInterface|DateInterval|int $ttl = 5_184_000): ?string
    {
        $key = "dl:$url";
        $content = Cache::get($key);

        if ($content) {
            return $content;
        }

        $content = file_get_contents($url);

        if (!$content) {
            return null;
        }

        Cache::put($key, $content, $ttl);

        return $content;
    }
}
