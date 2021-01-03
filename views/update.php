<h2>
	Mise à jour nécessaire
</h2>
<p>
	Bonjour, nous avons constaté que vous utilisiez une ancienne version de
	VICOPO. Nous avons depuis réalisé des optimisations qui peuvent économiser
	de nombreuses requêtes entre votre serveur et le nôtre. De plus il est
	possible que votre version ne puisse plus être maintenue dans le futur.
</p>
<p>
	Nous vous serions donc reconnaissants de mettre à jour votre script.
</p>
<p>
	Si vous utilisez l'API JavaScript front-end, merci de la retélécharger :<br>
	Version compressée : <a href="https://vicopo.selfbuild.fr/api.min.js">https://vicopo.selfbuild.fr/api.min.js</a><br>
	Source : <a href="https://vicopo.selfbuild.fr/api.js">https://vicopo.selfbuild.fr/api.js</a>
</p>
<p>
	Si vous utilisez un proxy, vous pouvez sans problème remplacer dans ce
	script <code>location.protocol+'//vicopo.selfbuild.fr'</code> par l'URL
	de votre proxy.
</p>
<p>
	Lorsque votre script sera à jour, nous vous invitons à envoyer un e-mail à
	<a href="mailto:vicopo@selfbuild.fr">vicopo@selfbuild.fr</a> pour que nous
	retirions rapidement la notice envoyée dans vos résponses. Veuillez y
	préciser la ou les IP concernées par la mise à jour.
	<?php
	if (!empty($_GET['ip'])) {
		$ip = $_GET['ip'];
		echo ' Cette notice a été levée pour ' .
			(preg_match('/^\d/', $ip) ? 'l\'IP' : 'le domaine') .
			' <strong>' . $ip . '</strong>';
	}
	?>
</p>
<p>
	Si vous rencontrez la moindre difficulté, n'hésitez pas à nous contacter à
	la même address : <a href="mailto:vicopo@selfbuild.fr">vicopo@selfbuild.fr</a>.
</p>
