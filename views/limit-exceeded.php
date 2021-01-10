<?php

// [$quota, $property] = explode('-', $_GET['ip'], 2);
$property = $_GET['ip'];
$isIP = preg_match('/^\d/', $_GET['ip']);
$type = $isIP ? "L'adresse IP" : "Le nom de domaine";

?>
<h2>
	Nombre de requêtes maximum atteint
</h2>
<p>
	<?= $type ?> <strong><?php echo $property === 'default' ? '' : htmlspecialchars($property); ?></strong> a dépassé le quota alloué.
</p>
<p>
    Si vous en êtes le propriétaire, <a href="/dashboard?property=<?= urlencode($property) ?>"><?php
        echo empty($_COOKIE['vuid'])
            ? 'créez un compte'
            : 'connectez-vous à votre compte';
        ?></a>
    et vérifiez-<?= $isIP ? 'la' : 'le' ?> pour allouer davantage de requêtes.
</p>
