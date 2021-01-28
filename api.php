<?php

try {

    define('LIMIT_EXCEEDED_ENABLED', false);
    define('UPDATE_NOTICE', false);
    define('UPDATE_ERROR', false);

    function adminDate($config, $formats, $date = null) {
        $date = $date ?? new DateTimeImmutable('now', new DateTimeZone($config['app']['admin_timezone'] ?? 'UTC'));

        if (is_array($formats)) {
            return array_map(static fn ($format) => adminDate($config, $format, $date), $formats);
        }

        return $date->format((string) $formats);
    }

    function logError($exception) {
        try {
            include_once __DIR__ . '/vendor/autoload.php';
            Log::warning($exception);
        } catch (Throwable $_) {}
    }

    function logIpDate($config) {
        global $pdo;

        [$date, $day] = adminDate($config, ['Y-m-d H:i:s', 'Y-m-d']);

        $pdo->prepare('
                INSERT INTO `log` (`date`, `day`, `ip`, `code`, `ville`, `referer`, `domain`)
                VALUES(:date, :day, :ip, :code, :ville, :referer, :domain)
            ')
            ->execute([
                'date' => $date,
                'day' => $day,
                'ip' => REMOTE_ADDR,
                'code' => (string) ($_GET['code'] ?? ''),
                'ville' => (string) ($_GET['city'] ?? ''),
                'referer' => REFERER,
                'domain' => DOMAIN,
            ]);
    }

    function format($type) {
        header('Content-type: ' . $type . '; charset=utf-8');
    }

    define('REMOTE_ADDR', filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP));

    if (!REMOTE_ADDR) {
        exit;
    }

    try {
        define('REFERER', $_SERVER['HTTP_REFERER'] ?? '');
        define('DOMAIN', preg_split('#[/:]#', preg_replace('`^[a-zA-Z0-9]+://`', '', REFERER))[0]);

        if (isset($_GET['search']) && !isset($_GET['city'], $_GET['code'])) {
            $_GET['search'] = preg_replace('`^(?:la|le|les)\s+(\d+)$`', '$1', $_GET['search']);
            $key = preg_match('`^\d+$`', $_GET['search']) ? 'code' : 'city';
            $_GET[$key] = $_GET['search'];
        }

        foreach (['city', 'code'] as $key) {
            $input = $_GET[$key] ?? '';
            $value = strtolower($input);

            if (in_array($value, [
                    'modifier',
                    'ajouter',
                    'ajouter un état',
                    'cloturer ou relancer le client',
                    'relancer le client',
                    'choisir',
                    'cnl',
                    'cotrex26',
                    'boyard',
                    'gt2c',
                    'envoyer',
                    'simplenews_block_form_1',
                    'chercher',
                    'ok',
                    'km',
                    'trouver une agence',
                    'se connecter',
                ]) || substr($value, 0, 4) === 'http' || strpos($value, '_') !== false) {
                $result = [
                    'input'  => $input,
                    'cities' => [],
                    'error'  => [
                        'message' => 'Not a city',
                    ],
                ];

                break;
            }
        }

        $limitedProperty = null;

        if (LIMIT_EXCEEDED_ENABLED && in_array(REMOTE_ADDR, [
                '88.174.126.68',
                '93.93.45.62',
                '84.246.226.225',
                '88.174.125.216',
                '51.83.99.161',
                '165.227.155.26',
                '109.234.161.88',
            ])) {
            $limitedProperty = REMOTE_ADDR;
        } elseif (UPDATE_ERROR && in_array(DOMAIN, [
                'appli.piegeur.com',
                'www.startpeople.fr',
            ])) {
            $limitedProperty = DOMAIN;
        }

        if ($limitedProperty) {
            $result = [
                'input'  => $_GET['city'] ?? $_GET['code'],
                'cities' => [
                    [
                        'code' => '00000',
                        'city' => 'ERREUR',
                    ],
                ],
                'error'  => [
                    'message' => 'Nombre de requêtes maximum atteint',
                    'explain' => 'https://vicopo.selfbuild.fr/limit-exceeded-'.$limitedProperty,
                ],
            ];
        }
    } catch (Throwable $exception) {
        logError($exception);
    }

    if (!isset($result)) {
        try {
            $config = @include __DIR__.'/bootstrap/cache/config.php' ?: [];

            $prodDbFile = __DIR__.'/bdd_prod.php';

            include_once file_exists($prodDbFile) ? $prodDbFile : __DIR__.'/bdd.php';

            if (!isset($GLOBALS['pdo']) && isset($pdo)) {
                $GLOBALS['pdo'] = $pdo;
            }

            $counted = false;
            $blockages = [];
            $quotaMax = 1000;

            foreach (['domain' => DOMAIN, 'ip' => REMOTE_ADDR] as $type => $property) {
                if ($property) {
                    $file = __DIR__.'/data/date-count/'.adminDate($config, '\yY/\mn/').$property.'.txt';
                    $count = (int) @file_get_contents($file);
                    $quotaReached = false;
                    $paid = false;
                    $tld = $type === 'domain' && preg_match('/^(?:.+)?\.([^.]+\.[a-z]+)$/', $property, $match)
                        ? $match[1]
                        : null;
                    $guestLimit = $config['app']['special_limit'][$tld]
                        ?? $config['app']['special_limit'][$property]
                        ?? $config['plan']['guest']['limit']
                        ?? 1000;

                    if ($count >= $guestLimit) {
                        $quotaReached = true;
                        $cacheFile = __DIR__."/data/properties/$type/$property.php";
                        $subscription = @file_exists($cacheFile) && time() - filemtime($cacheFile) < 1
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

                            $query = $GLOBALS['pdo']->prepare("
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

                            @file_put_contents($cacheFile, '<?php return '.var_export($subscription, true).";\n");
                        }

                        if ($subscription) {
                            $quotaReached = false;
                            $limit = $config['plan']['free']['limit'] ?? 5000;
                            $quotaMax = max($quotaMax, $limit);

                            if (!$counted && $count >= $limit) {
                                $userId = (int) $subscription->user_id;
                                $quota = match ($subscription->plan) {
                                    'start' => $config['plan']['start']['limit'] ?? 20000,
                                    'pro' => $config['plan']['pro']['limit'] ?? 200000,
                                    'premium' => $config['plan']['premium']['limit'] ?? INF,
                                    default => $limit,
                                };
                                $quotaMax = max($quotaMax, $quota) * ($config['app']['quota_factor'][$userId] ?? 1);
                                $date = new DateTimeImmutable($subscription->subscribed_at);
                                $diff = $date->diff(new DateTimeImmutable());
                                $month = $diff->y * 12 + $diff->m;
                                $subscriptionBaseDirectory = __DIR__.'/data/subscription-count/';
                                $subscriptionDirectory = $subscriptionBaseDirectory.'s'.$subscription->subscription_id;

                                if (!@is_dir($subscriptionDirectory)) {
                                    @mkdir($subscriptionDirectory);
                                }

                                $subscriptionFile = $subscriptionDirectory.'/m'.$month.'.txt';
                                $subscriptionCount = ((int) @file_get_contents($subscriptionFile)) + 1;
                                $quotaReached = $subscriptionCount > ($quotaMax - $limit);

                                if (!$quotaReached) {
                                    $counted = true;
                                    $paid = true;

                                    if (!file_exists($subscriptionFile) || $subscriptionCount > 1) {
                                        @file_put_contents($subscriptionFile, $subscriptionCount);
                                    }
                                }
                            }
                        }
                    }

                    $fileUpdates = [$file];

                    $blocked = $quotaReached && !$counted;

                    if ($blocked) {
                        $blockages[] = $property;
                        $fileUpdates[] = substr($file, 0, -4).'-blocked.txt';
                    }

                    if ($paid) {
                        $fileUpdates[] = substr($file, 0, -4).'-paid.txt';
                    }

                    if ($tld) {
                        $file = __DIR__.'/data/date-count/'.adminDate($config, '\yY/\mn/').$tld.'.txt';
                        $fileUpdates[] = $file;

                        if ($blocked) {
                            $fileUpdates[] = substr($file, 0, -4).'-blocked.txt';
                        }

                        if ($paid) {
                            $fileUpdates[] = substr($file, 0, -4).'-paid.txt';
                        }
                    }

                    foreach ($fileUpdates as $file) {
                        if (!file_exists($file)) {
                            @file_put_contents($file, '1');

                            continue;
                        }

                        $count = (int) @file_get_contents($file);

                        if ($count) {
                            @file_put_contents($file, $count + 1);
                        }
                    }
                }
            }

            $grace = false;

            if (!($config['app']['free_unlimited'] ?? true) && count($blockages)) {
                $blockage = reset($blockages);

                if ($config['app']['grace']['enabled'] ?? true) {
                    $file = __DIR__."/data/properties-grace/$blockage.txt";

                    if (!file_exists($file)) {
                        touch($file);
                        $grace = true;
                    } else {
                        $grace = in_array($blockage, $config['app']['grace']['unlimited'])
                            || time() - filemtime($file) < ($config['app']['grace']['days'] ?? 7) * 24 * 3600;
                    }
                }

                if (!$grace) {
                    $result = [
                        'input'  => $_GET['city'] ?? $_GET['code'],
                        'cities' => [
                            [
                                'code' => '00000',
                                'city' => 'ERREUR',
                            ],
                        ],
                        'error'  => [
                            'message' => 'Nombre de requêtes maximum atteint',
                            'explain' => 'https://vicopo.selfbuild.fr/limit-exceeded-'. /* $quotaMax . '-' . */
                                $blockage,
                        ],
                    ];
                }
            }
        } catch (Throwable $exception) {
            logError($exception);
        }

        if (!isset($result)) {
            try {
                logIpDate($config);
            } catch (Throwable $exception) {
                logError($exception);
            }

            $cities = UPDATE_NOTICE && in_array(DOMAIN, [
                    'www.louerserein.fr',
                    'deploiements.lgf.fr',
                    'appli.piegeur.com',
                ]) ? [
                [
                    'code' => 'https://vicopo.selfbuild.fr/update-' . DOMAIN,
                    'city' => 'https://vicopo.selfbuild.fr/update-' . DOMAIN,
                ]
            ] : [];
            $result = [
                'input' => $_GET['city'] ?? $_GET['code'],
                'cities' => $cities,
            ];

            if ($grace ?? false) {
                $result['error'] = [
                    'message' => 'Nombre de requêtes maximum atteint',
                    'explain' => 'https://vicopo.selfbuild.fr/limit-exceeded-' . /* $quotaMax . '-' . */ $blockage,
                ];
                $result['cities'] = [
                    [
                        'code' => '00000',
                        'city' => 'ERREUR',
                    ],
                ];
            }

            if (isset($_GET['city'])) {
                function normalize($string)
                {
                    $a = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
                    $b = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
                    $string = utf8_decode($string);
                    $string = strtr($string, utf8_decode($a), $b);
                    $string = strtolower($string);
                    $string = preg_replace('`[^a-zA-Z0-9_]+`', '-', $string);

                    return utf8_encode($string);
                }

                $cityCode = normalize((string) $_GET['city']);
                $start = substr($cityCode, 0, 2);
                $length = strlen($cityCode);

                if ($length > 1) {
                    if (file_exists($file = __DIR__.'/data/cities/' . substr($cityCode, 0, 2) . '.php')) {
                        $cities = include $file;

                        foreach ($cities as $_code => $_cities) {
                            foreach ($_cities as $city) {
                                if ($cityCode === substr(normalize($city), 0, $length)) {
                                    $result['cities'][] = [
                                        'code' => $_code,
                                        'city' => $city,
                                    ];
                                }
                            }
                        }
                    }

                    $codeStart = substr($cityCode, 0, 5);

                    if ($codeStart === substr('saint', 0, strlen($codeStart))) {
                        $substituteCode = 'st'.substr($cityCode, 5);
                        $substituteLength = strlen($substituteCode);
                        $cities = include __DIR__.'/data/cities/st.php';

                        foreach ($cities as $_code => $_cities) {
                            foreach ($_cities as $city) {
                                if ($substituteCode === substr(normalize($city), 0, $substituteLength)) {
                                    $result['cities'][] = [
                                        'code' => $_code,
                                        'city' => $city,
                                    ];
                                }
                            }
                        }
                    }
                }
            } else {
                function getPostalCode($code)
                {
                    $code = strtoupper($code);

                    if (
                        strlen($code) > 1 &&
                        substr($code, 0, 1) === '2' &&
                        in_array(substr($code, 1, 1), ['A', 'B'])
                    ) {
                        $code = '20'.substr($code, 2);
                    }

                    return $code;
                }

                function getDepartment($code)
                {
                    return match ('='.substr($code, 0, 3)) {
                        '=200', '=201' => '2A',
                        '=202', '=206' => '2B',
                        '=20' => 'corse',
                        default => substr($code, 0, 2),
                    };
                }

                $code = (string) $_GET['code'];
                $filter = match (substr($code, 0, 2)) {
                    '2A' => static fn($c) => substr($c, 0, 3) < 202,
                    '2B' => static fn($c) => substr($c, 0, 3) >= 202,
                    default => static fn() => true,
                };
                $length = strlen($code);

                if (
                    $length > 1 &&
                    preg_match('`^[0-9]{2,5}$`', $code = getPostalCode($code)) &&
                    file_exists($file = __DIR__.'/data/'.($dep = getDepartment($code)).'.php')
                ) {
                    $cities = include $file;

                    foreach ($cities as $_code => $_cities) {
                        if (substr($_code, 0, $length) === $code && $filter($_code)) {
                            foreach ($_cities as $city) {
                                $result['cities'][] = [
                                    'code' => $_code,
                                    'city' => $city,
                                ];
                            }
                        }
                    }
                }
            }
        }
    }

    foreach ([
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET',
        'Access-Control-Allow-Headers' => 'X-Requested-With',
        'Access-Control-Max-Age' => '1728000'
    ] as $name => $value) {
        header($name . ': ' . $value);
    }

    switch ($_GET['format'] ?? null) {
        case 'xml':
            format('text/xml');
            $xml = new SimpleXMLElement('<vicopo/>');
            $xml->addChild('input', $result['input']);

            foreach ($result['cities'] as $city) {
                $cities = $xml->addChild('cities');
                $cities->addChild('city', $city['city']);
                $cities->addChild('code', $city['code']);
            }

            if (isset($result['error'])) {
                $cities = $xml->addChild('error');
                $cities->addChild('message', $result['error']['message']);

                if (isset($result['error']['explain'])) {
                    $cities->addChild('explain', $result['error']['explain']);
                }
            }

            echo $xml->asXML();

            break;
        case 'yaml':
            format('text/x-yaml');

            echo yaml_emit($result);

            break;
        default:
            if (isset($_GET['callback'])) {
                format('text/javascript');

                echo $_GET['callback'] . '(' . json_encode($result) . ');';

                exit;
            }

            format('application/json');

            echo json_encode($result);
    }
} catch (Throwable $exception) {
    logError($exception);

    http_response_code(500);
    echo 'Oups, une erreur inattendue est survenue.';
    exit(1);
}
