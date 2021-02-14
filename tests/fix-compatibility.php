<?php

$replacements = [
    __DIR__ . '/../vendor/laravel/framework/src/Illuminate/Routing/Route.php' => [
        'rtrim($prefix,' => 'rtrim($prefix ?? \'\',',
    ],
    __DIR__ . '/../vendor/laravel/framework/src/Illuminate/Collections/Collection.php' => [
        'asort($items, $callback)' => 'asort($items, $callback ?? SORT_REGULAR)',
    ],
    __DIR__ . '/../vendor/symfony/console/Input/StringInput.php' => [
        '$match, null, $cursor' => '$match, 0, $cursor',
    ],
    __DIR__ . '/../vendor/laravel/framework/src/Illuminate/View/Compilers/BladeCompiler.php' => [
        'if (Str::startsWith($value, \'(\') && Str::endsWith($value, \')\')) {' =>
            '$value = $value ?? \'\'; if (Str::startsWith($value, \'(\') && Str::endsWith($value, \')\')) {',
    ],
    __DIR__ . '/../vendor/laravel/framework/src/Illuminate/Support/helpers.php' => [
        'htmlspecialchars($value,' => 'htmlspecialchars($value ?? \'\',',
    ],
    __DIR__ . '/../vendor/symfony/http-foundation/Response.php' => [
        'stripos($this->headers->get(\'Content-Disposition\')' =>
            'stripos($this->headers->get(\'Content-Disposition\') ?? \'\'',
        'preg_match(\'/MSIE (.*?);/i\', $request->server->get(\'HTTP_USER_AGENT\'), $match)' =>
            'preg_match(\'/MSIE (.*?);/i\', $request->server->get(\'HTTP_USER_AGENT\') ?? \'\', $match)',
    ],
    __DIR__ . '/../vendor/laravel/framework/src/Illuminate/Http/Request.php' => [
        'strcasecmp($this->server->get(\'HTTP_X_MOZ\')' => 'strcasecmp($this->server->get(\'HTTP_X_MOZ\') ?? \'\'',
        'strcasecmp($this->headers->get(\'Purpose\')' => 'strcasecmp($this->headers->get(\'Purpose\') ?? \'\'',
    ],
];

foreach ($replacements as $file => $replacement) {
    $contents = file_exists($file) ? file_get_contents($file) : null;

    if (!empty($contents)) {
        $newContents = strtr($contents, $replacement);

        if ($contents !== $newContents) {
            file_put_contents($file, $newContents);
        }
    }
}
