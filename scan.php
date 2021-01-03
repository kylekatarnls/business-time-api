<?php

exit;

$count = 0;
$start = 695314;

$pdo = new PDO('mysql:host=localhost;dbname=vicopo', 'vicopo', 'ZYZHoxIWLxjTk8Yw');
$records = [];

function recordsFlush() {

	global $pdo, $records;

	$pdo->exec('INSERT INTO `log` (`date`, `ip`, `code`, `ville`, `referer`, `domain`) VALUES' . implode(', ', $records));
	echo 'S';
	sleep(1);
	echo 'W';

	$records = [];
	
}

function record($ip, $code, $ville, $referer) {

	global $pdo, $records;

	$domain = explode('/', preg_replace('/^[a-zA-Z0-9]+:\/\//', '', $referer))[0];
	$records[] = '(NOW(), ' . $pdo->quote($ip) . ', ' . $pdo->quote($code) . ', ' . $pdo->quote($ville) . ', ' . $pdo->quote($referer) . ', ' . $pdo->quote($domain) . ')';
	
	if (count($records) > 100) {
		recordsFlush();
	}

}

function scanLine($line) {

	global $count, $start;

	if (empty($line) || ++$count <= $start) {
		return;
	}

	if (!preg_match(
		'/^(\S+)\s\S+\s\S+\s\[(\S+)[^\]]*\]\s"([A-Z]+)\s(\S+)\s\S+"\s200\s\d+\s"([^"]*)"\s/',
		$line,
		$match
	)) {
		return;
	}

	$ip = gethostbyname($match[1]);
	$match = array_slice($match, 2);

	if (strpos($url = trim($match[2], '/'), '/') !== false) {
		list($type, $search) = explode('/', $url);
	} else {
		if (!preg_match('/\?([^=]+)=([^&]+)/', $match[2], $subMatch)) {
			return;
		}
		$type = $subMatch[1];
		$search = $subMatch[2];
	}
	
	$code = intval($search);
	$ville = $code ? '' : $search;
	$referer = $match[3];
	$date = preg_split('/[\/:]+/', $match[0]);
	$m = [
		'Jan' => 1,
		'Feb' => 2,
		'Mar' => 3,
		'Apr' => 4,
		'May' => 5,
		'Jun' => 6,
		'Jul' => 7,
		'Aug' => 8,
		'Sep' => 9,
		'Oct' => 10,
		'Nov' => 11,
		'Dec' => 12,
	];
	$date = $date[2] . '-' . $m[$date[1]] . '-' . $date[0] . ' ' . $date[3] . ':' . $date[4] . ':' . $date[5];

	record($ip, $code, $ville, $referer);

}

$input = '';
$file = __DIR__ . '/logscan';
$size = filesize($file);
$handler = fopen($file, 'r');
$read = 0;
$time = 0;
$startRead = 0;

while (false !== ($input .= fread($handler, 512000))) {

	$read += 512000;

	if ($count >= $start) {
		if (!$time) {
			$startRead = $read;
			$time = microtime(true);
		}
		echo 'S';
		sleep(1);
		echo 'W';
	}

	$lines = explode("\n", $input);
	$input = array_pop($lines);

	foreach ($lines as $line) {

		scanLine($line);

	}

	echo PHP_EOL . PHP_EOL . $count . '    ' . round(100 * $read / $size, 1) . '%' . PHP_EOL;
	$spent = microtime(true) - $time;
	$remain = ($size - $read) * ($spent / ($read - $startRead));

}

fclose($handler);

scanLine($input);
recordsFlush();

