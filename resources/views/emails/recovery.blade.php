<div style="font-family: sans-serif;">
    Bonjour {{ $name }},<br /><br />

    Suite à un incendit du datacenter de Strasbourg d'OVH utilisé par Vicopo, nous avons
    dû installer l'application sur un nouveau serveur.<br /><br />

    Votre nouveau mot de passe&nbsp;:<br /><br />

    <strong style="font-family: monospace;">{{ $password }}</strong><br /><br />

    Nous vous recommandons de
    <a href="https://vicopo.selfbuild.fr/user/profile#change-password">changer ce mot de passe</a>
    lors de votre première connexion ou en suivant le lien ci-dessous&nbsp;:<br /><br />
    <a href="https://vicopo.selfbuild.fr/user/profile#change-password">https://vicopo.selfbuild.fr/user/profile#change-password</a><br /><br />

    Nous avons pu restaurer les données suivantes pour votre compte&nbsp;:
    <br /><br />

    <strong>Abonnement {{ $plan }}</strong><br /><br />

    Domaines et adresses IP&nbsp;:

    <ul>
        @foreach($properties as $property)
            <li>{{ $property }}</li>
        @endforeach
    </ul>

    Si une propriété est manquante nous vous prions de bien vouloir l'enregistrer sur
    <a href="https://vicopo.selfbuild.fr/dashboard">votre tableau de bord</a>&nbsp;:
    <a href="https://vicopo.selfbuild.fr/dashboard">https://vicopo.selfbuild.fr/dashboard</a><br /><br />

    Vous pouvez suivre l'avancement de la remise en service&nbsp;:<br /><br />
    <a href="https://www.ovh.com/fr/news/presse/cpl1785.dernieres-informations-notre-site-strasbourg">Suivi de l'incident OVH</a><br /><br />
    <a href="https://github.com/kylekatarnls/vicopo/issues/31">Suivi de la remise en service de Vicopo</a><br /><br />

    Nous vous prions d'accepter nos sincères excuses pour le désagrément et espérons recouvrire rapidement de l'incident
    si vous avez étez vous-même impacté,<br /><br />
    Vicopo
</div>
