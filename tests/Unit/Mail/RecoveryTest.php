<?php

namespace Tests\Unit\Mail;

use App\Mail\Recovery;
use Tests\TestCase;

final class RecoveryTest extends TestCase
{
    public function testRecoveryMail(): void
    {
        $recoveryMail = new Recovery([
            'name' => 'George',
            'password' => 'NotSoStrong',
            'plan' => 'Vicopo Start',
            'properties' => [
                'somedomain.com',
                '1.2.3.4',
            ],
        ]);
        $html = $recoveryMail->build()->render();

        $this->assertStringContainsString(
            '<strong style="font-family: monospace;">NotSoStrong</strong>',
            $html,
        );
        $this->assertStringContainsString(
            '<strong>Abonnement Vicopo Start</strong>',
            $html,
        );
        $this->assertStringContainsString(
            '<li>somedomain.com</li>',
            $html,
        );
        $this->assertStringContainsString(
            '<li>1.2.3.4</li>',
            $html,
        );

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/[\n\r]/', trim(strip_tags($html)))),
        ));

        $this->assertSame([
            'Bonjour George,',
            "Suite à un incendit du datacenter de Strasbourg d'OVH utilisé par Vicopo, nous avons",
            "dû installer l'application sur un nouveau serveur.",
            'Votre nouveau mot de passe est&nbsp;:',
            'NotSoStrong',
            'Nous vous recommandons de',
            'changer ce mot de passe',
            'lors de votre première connexion ou en suivant le lien ci-dessous&nbsp;:',
            'https://vicopo.selfbuild.fr/user/profile#change-password',
            'Nous avons pu restaurer les données suivantes pour votre compte&nbsp;:',
            'Abonnement Vicopo Start',
            'Domaines et adresses IP&nbsp;:',
            'somedomain.com',
            '1.2.3.4',
            "Si une propriété est manquante ou incorrecte, nous vous prions de bien vouloir l'enregistrer",
            'ou la supprimer sur',
            'votre tableau de bord&nbsp;:',
            'https://vicopo.selfbuild.fr/dashboard',
            "Vous pouvez suivre l'avancement de la remise en service&nbsp;:",
            "Suivi de l'incident OVH",
            'Suivi de la remise en service de Vicopo',
            "Nous vous prions d'accepter nos sincères excuses pour le désagrément,",
            'Vicopo',
        ], $lines);
    }
}
