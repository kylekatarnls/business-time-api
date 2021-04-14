<?php

// [$quota, $property] = explode('-', $_GET['ip'], 2);
$property = $_GET['ip'];
$isIP = preg_match('/^\d/', $_GET['ip']);
$type = $isIP ? "L'adresse IP" : "Le nom de domaine";
$guest = empty($_COOKIE['reg']);

?>
<h2>
	Nombre de requêtes maximum atteint
</h2>
<p>
	<?= $type ?> <strong><?php echo $property === 'default' ? '' : htmlspecialchars($property); ?></strong> a dépassé le quota alloué.
</p>
<p>
    Si vous en êtes le propriétaire, <a href="/dashboard?property=<?= urlencode($property) ?>"><?php
        echo $guest
            ? 'créez un compte'
            : 'connectez-vous à votre compte';
        ?></a>
    et vérifiez-<?= $isIP ? 'la' : 'le' ?> pour allouer davantage de requêtes.
</p>
<?php if ($guest && time() < strtotime('2021-06-01')): ?>
    <p>
        Suite à un incendit ayant détruit notre datacenter, les comptes gratuits créés entre janvier et mars
        ont été perdu, si vous vous étiez déjà inscrit, nous vous prions de bien vouloir recréer votre compte
        et vous prions de nous excuser pour la gène occasionnée.
    </p>
<?php endif; ?>

