<?php

function adminDate($config, $formats, $date = null) {
    $date = $date ?? new DateTimeImmutable('now', new DateTimeZone($config['app']['admin_timezone'] ?? 'UTC'));

    if (is_array($formats)) {
        return array_map(static fn ($format) => adminDate($config, $format, $date), $formats);
    }

    return $date->format((string) $formats);
}
