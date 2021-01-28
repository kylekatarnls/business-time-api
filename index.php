<?php

if(isset($_GET['code']) || isset($_GET['city']) || isset($_GET['search'])) {
	include __DIR__ . '/api.php';
	exit;
}

$config = @include __DIR__ . '/bootstrap/cache/config.php' ?: [];

define('JSFIDDLE_USER', 'KyleKatarn');
define('HOST', ($_SERVER['HTTP_HOST'] ?? null) === 'vicopo.ovnicap.com'
    ? (
        ($_SERVER['REQUEST_SCHEME'] ?? null) === 'https' ||
        ($_SERVER['HTTPS'] ?? null) === 'on' ||
        ((int) ($_SERVER['SERVER_PORT'] ?? 0)) === 443
            ? 'https'
            : 'http'
    ) . '://' . $_SERVER['HTTP_HOST']
    : 'https://vicopo.selfbuild.fr');

function jsfiddle($code, $tabs = 'js,html,result'): void {
    ?><div class="example">
        <script async src="https://jsfiddle.net/<?php echo JSFIDDLE_USER; ?>/<?php echo $code; ?>/embed/<?php echo $tabs; ?>/"></script>
    </div><?php
}

?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>VICOPO - API Ville et Code postal</title>
	<link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
	<link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Oswald:700|Titillium+Web|Fira+Mono">
	<link rel="stylesheet" type="text/css" href="index.css">

    <script type="text/javascript">
        var _paq = window._paq = window._paq || [];
        <?php /* tracker methods like "setCustomDimension" should be called before "trackPageView" */ ?>
        _paq.push(['trackPageView']);
        _paq.push(['enableLinkTracking']);
        (function() {
            var u="//<?= $config['analytics']['host'] ?? 'piwik.selfbuild.fr' ?>/";
            _paq.push(['setTrackerUrl', u+'matomo.php']);
            _paq.push(['setSiteId', '<?= $config['analytics']['id'] ?? '24' ?>']);
            var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
            g.type='text/javascript'; g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
        })();
    </script>
    <noscript><p><img src="//<?= $config['analytics']['host'] ?? 'piwik.selfbuild.fr' ?>/matomo.php?idsite=<?= $config['analytics']['id'] ?? '24' ?>&amp;rec=1" style="border:0;" alt="" /></p></noscript>
</head>
<body>
	<header>
		<img src="city.jpg" alt="VICOPO" width="168" height="85" class="city-background">
		<h1>
            <?php if ($config['app']['features']['account'] ?? false): ?>
                <a href="/dashboard<?php
                    if (!empty($_GET['ip'])) {
                        echo '?property=' . urlencode($_GET['ip']);
                    }
                ?>" class="account-button"><?= match($config['app']['locale']) {
                    'fr' => 'Compte',
                    default => 'Account',
                } ?></a>
            <?php endif; ?>

			VICOPO
		</h1>
	</header>
	<?php
	$view = isset($_GET['view']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['view']) : '';
	include empty($view) || !file_exists($view = __DIR__ . '/views/' . $view . '.php')
		? __DIR__ . '/views/index.php'
		: $view;
	?>
	<footer>
		<a href="https://github.com/kylekatarnls/vicopo">vicopo</a>
        <?php if ($config['app']['features']['contact'] ?? false): ?>
            &nbsp; | &nbsp;
            <a href="/contact">Contact</a>
        <?php endif; ?>
	</footer>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<script type="text/javascript" src="index.js"></script>
</body>
</html>
