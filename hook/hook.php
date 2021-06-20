<?php

$token = $_GET['token'] ?? null;

if ($token !== 'jlV2H_hndjbH2VTVDgvUFTZVGHJdhgVGZCVzDE2') {
    exit(1);
}

echo "Starting hook.\n";

echo
    'curl http://localhost:4563/?hook=' . urlencode(__DIR__ . '/build.oc-hook.php') .
    ' 2> /dev/null & echo $!' . "\n";

echo shell_exec(
    'curl http://localhost:4563/?hook=' . urlencode(__DIR__ . '/build.oc-hook.php') .
    ' 2> /dev/null & echo $!'
) . "\n";

echo "Hook started.\n";
