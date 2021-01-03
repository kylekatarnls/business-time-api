<?php

declare(strict_types=1);

$xml = @simplexml_load_file(__DIR__.'/../coverage.xml');

$metrics = $xml?->project?->metrics;
$statements = (string) $metrics['statements'] ?? 0;
$coveredStatements = (string) $metrics['coveredstatements'] ?? 0;

$status = sprintf(
    '%s %% — %s / %s',
    number_format(100.0 * $coveredStatements / max($statements, 1), 2),
    $coveredStatements,
    $statements,
);

if (!in_array('--send', $argv ?? [])) {
    echo $status;
    exit;
}

$repo = getenv('GITHUB_REPOSITORY');
$token = getenv('GITHUB_TOKEN');
$sha = getenv('GITHUB_SHA');

echo "$repo: $sha\n";

$ch = curl_init("https://api.github.com/repos/$repo/statuses/$sha");
$payload = json_encode([
    'state' => 'success',
    'description' => $status,
    'context' => 'Coverage',
]);
curl_setopt($ch, CURLOPT_USERAGENT, 'PHP');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    'Content-Type:application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if (!empty($error)) {
    echo $error;
    exit(1);
}

echo json_encode(json_decode($result), JSON_PRETTY_PRINT);
exit(0);
