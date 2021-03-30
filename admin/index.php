<?php

declare(strict_types=1);

if (empty($_SERVER['HTTPS']) && (getallheaders()['X-Forwarded-Proto'] ?? null) !== 'https') {
	header('Location: https://vicopo.selfbuild.fr/admin/');
	exit;
}

session_start();

if (isset($_POST['user'], $_POST['pass'])) {

	$options = [
	    'cost' => 12,
	];
	$user = '$2y$12$0wQztIRjc/pTmmIEYEasMe05mb2OOpJlMPpp3ZmF.yQ0l4wGiw13u';
	$pass = '$2y$12$9TjFLLxViN1z1vmgvLdlluVX3VDWluTC1uK0b.N5OZcgeSpChHl1S';

	if (
		password_verify(strtolower($_POST['user']), $user) &&
		password_verify($_POST['pass'], $pass)
	) {
		$_SESSION['id'] = 1;
		header('Location: /admin/');
	} else {
		header('Location: /admin/?ko');
	}
	exit;

}

?><!doctype html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<title>Admin</title>
	<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
	<?php if (!empty($_SESSION['id'])) { ?>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/jquery.jqplot.min.css">
	<?php } ?>
</head>
<body>
	<?php
	include
		'resources/' .
		(empty($_SESSION['id'])
			? 'login'
			: 'dashboard'
		) .
		'.php';
	?>
	<script src="https://code.jquery.com/jquery-2.2.4.min.js" integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
	<?php if (!empty($_SESSION['id'])) { ?>
		<script type="text/javascript" src="/js/jquery.canvasjs.min.js"></script>
	<?php } ?>
	<script src="app.js"></script>
</body>
</html>
