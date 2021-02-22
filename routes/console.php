<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('i18n', function () {
    include_once __DIR__ . '/utils/crawl.php';

    $appPath = __DIR__ . '/..';
    $file = "$appPath/resources/lang/fr.json";
    $data = json_decode(file_get_contents($file), true);
    crawl($appPath, $data);

    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
    );
})->purpose('Extract translations');

Artisan::command('db:prod', function () {
    file_put_contents(
        'bdd_prod.php',
        "<?php\n\n".
        "\$pdo = new PDO('mysql:host=localhost;dbname=" .
        config('database.connections.mysql.database') ."', '" .
        config('database.connections.mysql.username') ."', '" .
        config('database.connections.mysql.password') ."');\n",
    );
})->purpose('Create bdd_prod.php from .env file');

Artisan::command('refresh', function () {
    chdir(__DIR__ . '/..');

    require 'job/refresh.php';
})->purpose('Refresh cache files');

Artisan::command('directories', function () {
    $dir = __DIR__ . '/../data/stripe';

    if (!is_dir($dir)) {
        mkdir($dir);
    }

    $year = (int) date('Y');

    for ($y = 0; $y < 20; $y++) {
        $dir = __DIR__ . '/../data/date-count/y' . ($year + $y);

        if (!is_dir($dir)) {
            mkdir($dir);
        }

        for ($m = 1; $m <= 12; $m++) {
            $subDir = $dir . '/m' . $m;

            if (!is_dir($subDir)) {
                mkdir($subDir);
            }
        }
    }
})->purpose('Refresh cache files');

Artisan::command('seed:days', function () {
    global $pdo;

    require __DIR__ . '/../bdd_prod.php';

    echo $pdo->exec('UPDATE `log` SET `day` = DATE(`date`) WHERE `day` IS NULL LIMIT 1000') . "\n";
})->purpose('Display an inspiring quote');
