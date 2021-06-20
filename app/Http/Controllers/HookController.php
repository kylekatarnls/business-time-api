<?php

namespace App\Http\Controllers;

/**
 * @codeCoverageIgnore
 */
final class HookController extends AbstractController
{
    public function deploy()
    {
        echo "Starting hook.\n";

        $hook = realpath(__DIR__.'/../../../hook/build.oc-hook.php');
        $command = 'curl http://localhost:4563/?hook=' . urlencode($hook) . ' 2> /dev/null & echo $!';

        echo "$command\n";

        echo shell_exec($command) . "\n";

        echo "Hook started.\n";
    }
}
