<?php

/** @var PDO $pdo */
$pdo = null;

$prodDbFile = __DIR__ . '/../../bdd_prod.php';

include_once file_exists($prodDbFile) ? $prodDbFile : __DIR__ . '/../../bdd.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));");

$query = $pdo->query('
    SELECT `date`, `count`
    FROM `daily_log`
    WHERE `date` >= DATE_SUB(DATE(NOW()), INTERVAL ' . (GLOBAL_INTERVAL + 1) . ' DAY)
');
$counts = [];

while ($data = $query->fetch(PDO::FETCH_OBJ)) {
    $counts[$data->date] = (int) $data->count;
}

$query->closeCursor();

$now = new DateTimeImmutable();

for ($i = GLOBAL_INTERVAL; $i > 0; $i--) {
    $day = $now->modify("-$i days")->format('Y-m-d');

    if (!isset($counts[$day])) {
        $count = (int) $pdo->query("
            SELECT COUNT(`id`)
            FROM `log`
            WHERE DATE(`date`) = '$day'
        ")->fetchColumn();
        $counts[$day] = $count;
        $pdo->exec("
            INSERT INTO `daily_log` (`date`, `count`)
            VALUES('$day', $count)
        ");

        foreach (['code', 'ville', 'ip', 'domain'] as $key) {
            $query = $pdo->query("
                SELECT `$key` as `value`, COUNT(`id`) AS `count`
                FROM `log` WHERE DATE(`date`) = '$day'
                GROUP BY `$key`
            ");

            while ($data = $query->fetch(PDO::FETCH_OBJ)) {
                $count = (int) $data->count;
                $value = $data->value;
                $pdo->prepare('
                    INSERT INTO `daily_filtered_log` (`date`, `key`, `value`, `count`)
                    VALUES(?, ?, ?, ?)
                ')->execute([$day, $key, substr($value, 0, 220), $count]);
            }

            $query->closeCursor();
        }
    }
}
