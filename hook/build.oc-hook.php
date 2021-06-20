<?php

$folder = __DIR__ . '/../storage/logs/deploy';

if (!@is_dir($folder)) {
    mkdir($folder, 0777, true);
}

$file = fopen($folder . '/' . (new DateTime('now UTC'))->format('Y-m-d--H-i-s--u') . '.log', 'a+');

chdir(__DIR__ . '/..');

fwrite($file, (new DateTime('now'))->format('Y-m-d H:i:s.u e') . "\n");
fwrite($file, shell_exec('git fetch origin master') . "\n");
fwrite($file, shell_exec('git reset --hard origin/master') . "\n");
$log = shell_exec('git log -n 1');
fwrite($file, "$log\n");
fwrite($file, shell_exec('composer i') . "\n");
fwrite($file, shell_exec('npm run production') . "\n");
fwrite($file, shell_exec('php artisan migrate') . "\n");
fwrite($file, shell_exec('php artisan config:cache') . "\n");
fwrite($file, shell_exec('php artisan view:cache') . "\n");
fwrite($file, shell_exec('php artisan route:cache') . "\n");
fwrite($file, shell_exec('php artisan event:cache') . "\n");
fwrite($file, (new DateTime('now'))->format('Y-m-d H:i:s.u e') . "\n");

//$ch = curl_init('https://hooks.slack.com/services/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');
//$payload = json_encode([
//    'text' => "New version deployed on https://business-time-api.selfbuild.fr/\n\n```\n$log\n```",
//]);
//curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
//curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
//curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//fwrite($file, curl_exec($ch) . "\n");
//curl_close($ch);
fclose($file);

