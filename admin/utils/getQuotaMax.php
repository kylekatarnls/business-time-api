<?php

function getQuotaMax(PDO $pdo, string $type, string $property): array {
    static $config = null;

    if ($config === null) {
        $config = @include __DIR__.'/../../bootstrap/cache/config.php' ?: [];
    }

    $counted = false;
    $blockages = [];
    $quotaMax = 1000;

    $file = __DIR__.'/../../data/date-count/'.adminDate($config, '\yY/\mn/').$property.'.txt';
    $count = (int) @file_get_contents($file);
    $quotaReached = false;
    $userId = null;
    $tld = $type === 'domain' && preg_match('/^(?:.+)?\.([^.]+\.[a-z]+)$/', $property, $match)
        ? $match[1]
        : null;
    $guestLimit = $config['app']['special_limit'][$tld]
        ?? $config['app']['special_limit'][$property]
        ?? $config['plan']['guest']['limit']
        ?? 1000;

    if ($count >= $guestLimit) {
        $quotaReached = true;
        $cacheFile = __DIR__."/../../data/properties/$type/$property.php";
        $subscription = @file_exists($cacheFile) && time() - filemtime($cacheFile) < 3600
            ? @include $cacheFile
            : null;

        if (!$subscription) {
            $tldClause = '';
            $queryParams = [
                'type'     => $type,
                'property' => $property,
            ];

            if ($tld) {
                $tldClause = ' OR `value` = :tld';
                $queryParams['tld'] = $tld;
            }

            $query = $pdo->prepare("
                SELECT s.*, a.*,
                       s.`id` AS `subscription_id`,
                       s.`created_at` AS `subscribed_at`,
                       s.`updated_at` AS `refreshed_at`,
                       s.`name` AS `plan`
                FROM `api_authorizations` as a
                LEFT JOIN `subscriptions` AS s ON s.user_id = a.user_id AND s.stripe_status = 'active'
                WHERE `type` = :type AND (`value` = :property$tldClause)
            ");
            $query->execute($queryParams);
            $subscription = $query->fetch(PDO::FETCH_OBJ);
        }

        if ($subscription) {
            $quotaReached = false;
            $limit = $config['plan']['free']['limit'] ?? 5000;
            $quotaMax = max($quotaMax, $limit);

            if (!$counted && $count >= $limit) {
                $userId = (int) $subscription->user_id;
                $quota = match ($subscription->plan) {
                    'start' => $config['plan']['guest']['limit'] ?? 20000,
                    'pro' => $config['plan']['pro']['limit'] ?? 200000,
                    'premium' => $config['plan']['premium']['limit'] ?? INF,
                    default => $config['plan']['pro']['limit'] ?? 20000,
                };
                $quotaMax = max($quotaMax, $quota);
                $date = new DateTimeImmutable($subscription->subscribed_at);
                $diff = $date->diff(new DateTimeImmutable());
                $month = $diff->y * 12 + $diff->m;
                $subscriptionBaseDirectory = __DIR__.'/data/subscription-count/';
                $subscriptionDirectory = $subscriptionBaseDirectory.'s'.$subscription->subscription_id;

                $subscriptionFile = $subscriptionDirectory.'/m'.$month.'.txt';
                $subscriptionCount = ((int) @file_get_contents($subscriptionFile)) + 1;
                $quotaReached = $subscriptionCount > $quota;
            }
        }
    }

    $blocked = $quotaReached && !$counted;

    $graceStarted = false;
    $graceEnded = false;
    $graceStartedAt = time();
    $graceDuration = ($config['app']['grace']['days'] ?? 7) * 24 * 3600;
    $graceProrate = 0;

    if (!($config['app']['free_unlimited'] ?? true) && count($blockages)) {
        $blockage = reset($blockages);

        if ($config['app']['grace']['enabled'] ?? true) {
            $file = __DIR__."/../../data/properties-grace/$blockage.txt";

            if (file_exists($file)) {
                $graceStarted = true;
                $graceStartedAt = filemtime($file);
                $grace = time() - $graceStartedAt < $graceDuration;
                $graceEnded = !$grace;
                $graceProrate = (time() - $graceStartedAt) / $graceDuration;
            }
        }
    }

    return [
        'count' => json_encode($count),
        'userId' => json_encode($userId),
        'quota' => json_encode($quotaMax),
        'quotaReached' => json_encode($quotaReached),
        'blocked' => json_encode($blocked),
        'graceStarted' => json_encode($graceStarted),
        'graceEnded' => json_encode($graceEnded),
        'graceStartedAt' => $graceStarted ? date('Y-m-d H:i:s', $graceStartedAt) : 'null',
        'graceDuration' => json_encode($graceDuration),
        'graceProrate' => number_format(100 * $graceProrate, 1) . '%',
    ];
}
