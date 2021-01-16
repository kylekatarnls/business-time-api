<?php

declare(strict_types=1);

error_reporting(-1);
ini_set('display_errors', 'on');

define('GLOBAL_TOP', isset($_GET['top']) && $_GET['top'] >= 1 ? intval($_GET['top']) : 10);
define('GLOBAL_INTERVAL', isset($_GET['interval']) && $_GET['interval'] >= 1 ? intval($_GET['interval']) : 20);
define('EMPTY_STRING', '(empty)');

$prodDbFile = __DIR__ . '/../../bdd_prod.php';

$pdo = null;

include_once file_exists($prodDbFile) ? $prodDbFile : __DIR__ . '/../../bdd.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));");

define('GLOBAL_WHERE',
	'l.`date` > DATE_SUB(DATE(NOW()), INTERVAL ' . GLOBAL_INTERVAL . ' DAY)' .
	(empty($_GET['ip']) ? '' : ' AND l.ip = \'' . preg_replace('/[^0-9.]/', '', $_GET['ip']) . '\'') .
	(empty($_GET['code']) ? '' : ' AND l.code = \'' . preg_replace('/[^0-9]/', '', $_GET['code']) . '\'') .
	(empty($_GET['ville']) ? '' : ' AND l.ville = ' . $pdo->quote($_GET['ville'])) .
	(empty($_GET['domain']) ? '' : ' AND l.domain = ' . $pdo->quote($_GET['domain'] === EMPTY_STRING ? '' : $_GET['domain']))
);

$data = $pdo->query('
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
			<div class="col-xs-12" data-graph="<?php echo htmlspecialchars(json_encode($data)); ?>" style="height: 200px;"></div>
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
										<a href="?domain=<?php echo urlencode($domain); ?>"><?php echo $domain; ?></a>
										<a href="http://<?php echo $row->domain; ?>"><img src="/admin/external-link.png"></a>
									</td>
									<td><?php echo number_format((float) $row->count, 0, ',', ' '); ?></td>
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
										<a href="?ip=<?php echo $row->ip; ?>"><?php echo $row->ip; ?></a>
										<a href="http://<?php echo $row->ip; ?>"><img src="/admin/external-link.png"></a>
									</td>
									<td><?php echo number_format((float) $row->count, 0, ',', ' '); ?></td>
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
