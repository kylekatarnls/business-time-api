<?php

declare(strict_types=1);

error_reporting(-1);
ini_set('display_errors', 'on');

define('GLOBAL_TOP', isset($_GET['top']) && $_GET['top'] >= 1 ? intval($_GET['top']) : 10);
define('GLOBAL_INTERVAL', isset($_GET['interval']) && $_GET['interval'] >= 1 ? intval($_GET['interval']) : 20);
define('EMPTY_STRING', '(empty)');

$prodDbFile = __DIR__ . '/../../bdd_prod.php';

$config = @include __DIR__ . '/../../bootstrap/cache/config.php' ?: [];

function countStyle($count, array $config)
{
    $monthlyEstimatedCount = $count * 30 / GLOBAL_INTERVAL;
    $limits = array_values(array_map(static fn (array $plan) => $plan['limit'], $config['plan'] ?? []));

    $rank = 0;

    foreach ($limits as $index => $limit) {
        if ($monthlyEstimatedCount >= $limit) {
            $rank = $index + 1;
        }
    }

    if ($rank) {
        $rate = 1 - $rank / count($limits);

        return ' style="background: hsl(' . round(130 * $rate) . 'deg 100% ' . (76 + round($rate * 16)) . '%);"';
    }

    return '';
}

/** @var PDO $pdo */
$pdo = null;

include_once file_exists($prodDbFile) ? $prodDbFile : __DIR__ . '/../../bdd.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));");

$query = $pdo->query('
    SELECT `date`, `count`
    FROM `daily_log`
    WHERE `date` >= DATE_SUB(DATE(NOW()), INTERVAL ' . GLOBAL_INTERVAL . ' DAY)
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
                ')->execute([$day, $key, $value, $count]);
            }

            $query->closeCursor();
        }
    }
}

define('HAS_FILTER', !(empty($_GET['ip']) && empty($_GET['code']) && empty($_GET['ville']) && empty($_GET['domain'])));

define('GLOBAL_WHERE',
	'l.`date` > DATE_SUB(DATE(NOW()), INTERVAL ' . GLOBAL_INTERVAL . ' DAY)' .
	(empty($_GET['ip']) ? '' : ' AND l.ip = \'' . preg_replace('/[^0-9.]/', '', $_GET['ip']) . '\'') .
	(empty($_GET['code']) ? '' : ' AND l.code = \'' . preg_replace('/[^0-9]/', '', $_GET['code']) . '\'') .
	(empty($_GET['ville']) ? '' : ' AND l.ville = ' . $pdo->quote($_GET['ville'])) .
	(empty($_GET['domain']) ? '' : ' AND l.domain = ' . $pdo->quote($_GET['domain'] === EMPTY_STRING ? '' : $_GET['domain']))
);

/** @var PDOStatement $query */
$query = $pdo->query('SELECT * FROM `api_authorizations`');
$authorizations = [];

while ($data = ($query->fetch(PDO::FETCH_OBJ))) {
    $authorizations[$data->type . ':' . $data->value] = $data->user_id;
}

$data = $pdo->query(
    HAS_FILTER
        ? '
	SELECT
		CONCAT(DATE(d.`date`), \' 01:PM\'),
		COUNT(l.`id`)
	FROM (
		SELECT start_date + INTERVAL num DAY `date` FROM
		(SELECT tth*10000+th*1000+h*100+t*10+u AS num FROM
		(SELECT 0 tth UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
		UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) TTHN,
		(SELECT 0 th UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
		UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) THN,
		(SELECT 0 h UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
		UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) HN,
		(SELECT 0 t UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
		UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) TN,
		(SELECT 0 u UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
		UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) UN) A,
		(SELECT DATE_SUB(NOW(), INTERVAL ' . (GLOBAL_INTERVAL - 1) . ' DAY) start_date) B,
		(SELECT NOW() end_date) C
		WHERE start_date + INTERVAL num DAY <= end_date
		ORDER BY num
	) AS d
	LEFT JOIN log AS l
		ON l.`day` = DATE(d.`date`)
		AND ' . GLOBAL_WHERE . '
	GROUP BY l.`day`
	ORDER BY d.`date`
	LIMIT ' . GLOBAL_INTERVAL
        : '
	SELECT CONCAT(`date`, \' 01:PM\'), `count`
	FROM `daily_log`
	ORDER BY `date`
	WHERE `date` > DATE(DATE_SUB(NOW(), INTERVAL ' . GLOBAL_INTERVAL . ' DAY))
 	LIMIT ' . GLOBAL_INTERVAL
)->fetchAll(PDO::FETCH_NUM);

foreach ($data as &$d) {
	$d[1] |= 0;
}

$domains = $pdo->query('
	SELECT
		l.domain,
		COUNT(*) AS count
	FROM log AS l
	WHERE ' . GLOBAL_WHERE . '
	GROUP BY l.domain
	ORDER BY count DESC
	LIMIT ' . GLOBAL_TOP
)->fetchAll(PDO::FETCH_OBJ);

$ips = $pdo->query('
	SELECT
		l.ip,
		COUNT(*) AS count
	FROM log AS l
	WHERE ' . GLOBAL_WHERE . '
	GROUP BY l.ip
	ORDER BY count DESC
	LIMIT ' . GLOBAL_TOP
)->fetchAll(PDO::FETCH_OBJ);

$codes = $pdo->query('
	SELECT
		l.code,
		COUNT(*) AS count
	FROM log AS l
	WHERE ' . GLOBAL_WHERE . '
	AND l.code != \'\'
	GROUP BY l.code
	ORDER BY count DESC
	LIMIT ' . GLOBAL_TOP
)->fetchAll(PDO::FETCH_OBJ);

$villes = $pdo->query('
	SELECT
		l.ville,
		COUNT(*) AS count
	FROM log AS l
	WHERE ' . GLOBAL_WHERE . '
	AND l.ville != \'\'
	GROUP BY l.ville
	ORDER BY count DESC
	LIMIT ' . GLOBAL_TOP
)->fetchAll(PDO::FETCH_OBJ);

?>
<div class="row" style="margin: 50px auto; max-width: 1400px;">
	<div class="col-xs-12">
		<div class="row">
			<div class="col-xs-12" data-graph="<?= htmlspecialchars(json_encode($data)) ?>" style="height: 200px;"></div>
		</div>
		<div class="row">
			<div class="col-xs-6">
				<div class="well">
					<div class="panel panel-default">
						<table class="table">
							<tr>
								<th>Domaine</th>
								<th>Hits</th>
							</tr>
							<?php
							foreach ($domains as $row) {
								$domain = empty($row->domain) ? EMPTY_STRING : $row->domain;
								?>
								<tr>
									<td>
										<a href="?domain=<?= urlencode($domain) ?>"><?= $domain ?></a>
										<a href="http://<?= $row->domain ?>"><img src="/admin/external-link.png"></a>
                                        <?php if (isset($authorizations['domain:' . $row->domain])) { ?>
                                            <a href="/dashboard/<?= $authorizations['domain:' . $row->domain] ?>">🙍</a>
                                        <?php } ?>
									</td>
									<td <?= countStyle($row->count, $config) ?>><?= number_format((float) $row->count, 0, ',', ' ') ?></td>
								</tr>
								<?php
							}
							?>
						</table>
                        <?php
                        if (isset($_GET['domain']) && $_GET['domain'] !== EMPTY_STRING) {
                            require_once __DIR__ . '/../../vendor/autoload.php';
                            require_once __DIR__ . '/../utils/adminDate.php';
                            require_once __DIR__ . '/../utils/getQuotaMax.php';
                            $quotaData = getQuotaMax($pdo, 'domain', $_GET['domain']);
                            $quotaData['ip'] = gethostbyname($_GET['domain']);

                            echo '<ul>';

                            foreach ($quotaData as $key => $value) {
                                echo '<li>' . strtolower(preg_replace('/[A-Z]/', ' $0', $key)) .
                                    ': <strong>' . $value . '</strong></li>';
                            }

                            echo '</ul>';
                        }
                        ?>
					</div>
				</div>
			</div>
			<div class="col-xs-6">
				<div class="well">
					<div class="panel panel-default">
						<table class="table">
							<tr>
								<th>IP</th>
								<th>Hits</th>
							</tr>
							<?php
							foreach ($ips as $row) {
								?>
								<tr>
									<td>
										<a href="?ip=<?= $row->ip ?>"><?= $row->ip ?></a>
										<a href="http://<?= $row->ip ?>"><img src="/admin/external-link.png"></a>
                                        <?php if (isset($authorizations['ip:' . $row->ip])) { ?>
                                            <a href="/dashboard/<?= $authorizations['ip:' . $row->ip] ?>">🙍</a>
                                        <?php } ?>
									</td>
									<td <?= countStyle($row->count, $config) ?>><?= number_format((float) $row->count, 0, ',', ' ') ?></td>
								</tr>
								<?php
							}
							?>
						</table>
                        <?php
                        if (!empty($_GET['ip'])) {
                            require_once __DIR__ . '/../../vendor/autoload.php';
                            require_once __DIR__ . '/../utils/adminDate.php';
                            require_once __DIR__ . '/../utils/getQuotaMax.php';
                            $quotaData = getQuotaMax($pdo, 'ip', $_GET['ip']);
                            $quotaData['host'] = gethostbyaddr($_GET['ip']);

                            echo '<ul>';

                            foreach ($quotaData as $key => $value) {
                                echo '<li>' . strtolower(preg_replace('/[A-Z]/', ' $0', $key)) .
                                    ': <strong>' . $value . '</strong></li>';
                            }

                            echo '</ul>';
                        }
                        ?>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-6">
				<div class="well">
					<div class="panel panel-default">
						<table class="table">
							<tr>
								<th>Code postal</th>
								<th>Hits</th>
							</tr>
							<?php
							foreach ($codes as $row) {
								?>
								<tr>
									<td><a href="?code=<?php echo $row->code; ?>"><?php echo $row->code; ?></a></td>
									<td><?php echo number_format((float) $row->count, 0, ',', ' '); ?></td>
								</tr>
								<?php
							}
							?>
						</table>
					</div>
				</div>
			</div>
			<div class="col-xs-6">
				<div class="well">
					<div class="panel panel-default">
						<table class="table">
							<tr>
								<th>Ville</th>
								<th>Hits</th>
							</tr>
							<?php
							foreach ($villes as $row) {
								?>
								<tr>
									<td><a href="?ville=<?php echo $row->ville; ?>"><?php echo $row->ville; ?></a></td>
									<td><?php echo number_format((float) $row->count, 0, ',', ' '); ?></td>
								</tr>
								<?php
							}
							?>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
