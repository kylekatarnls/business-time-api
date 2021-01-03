<?php

function crawl($path, &$data)
{
    if (is_file($path)) {
        preg_match_all(
            '/(?<![a-zA-Z0-9_])(?:__|trans|trans_choice)\s*\(\s*(
                "(?:\\\\[\\S\\s]|[^"\\\\])*"|
                \'(?:\\\\[\\S\\s]|[^\'\\\\])*\'
            )/x',
            file_get_contents($path),
            $matches,
        );

        foreach ($matches[1] as $string) {
            $string = eval("return $string;");

            if (preg_match('/^([^\s.]+)\.\S*$/', $string, $match) &&
                file_exists(__DIR__ . '/../resources/lang/fr/' . $match[1] . '.php')
            ) {
                continue;
            }

            if (preg_match('/^(diff|period)_/', $string, $match)) {
                continue;
            }

            if (in_array($string, [
                'generated-value',
            ], true)) {
                continue;
            }

            if (!isset($data[$string])) {
                $data[$string] = $string;
            }
        }

        return;
    }

    if (is_dir($path)) {
        foreach (scandir($path) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            crawl("$path/$file", $data);
        }
    }
}
