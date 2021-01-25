<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class PlanTest extends DuskTestCase
{
    public function testRegisterLoginAndAddDomain(): void
    {
        User::where(['email' => 'ziggy@star.dust'])->forceDelete();

        $this->browse(static function (Browser $browser) {
            $browser
                ->visit('/')
                ->waitForText('Compte', 8)
                ->clickLink('Compte')
                ->waitForText("S'INSCRIRE", 8)
                ->assertFocused('[name="name"]')
                ->type('[name="name"]', 'David Bowie')
                ->keys('[name="name"]', '{tab}')
                ->assertFocused('[name="email"]')
                ->type('[name="email"]', 'ziggy@star.dust')
                ->keys('[name="email"]', '{tab}')
                ->assertFocused('[name="password"]')
                ->type('[name="password"]', '58mkop')
                ->keys('[name="password"]', '{tab}')
                ->assertFocused('[name="password_confirmation"]')
                ->type('[name="password_confirmation"]', 'different')
                ->keys('[name="password_confirmation"]', '{tab}')
                ->assertFocused('[type="checkbox"]')
                ->check('[type="checkbox"]')
                ->press("S'INSCRIRE")
                ->waitForText("Oups ! Quelque chose s'est mal passé.", 8)
                ->assertSee('Le champ mot de passe doit avoir au moins 8 caractères.')
                ->assertSee('Le champ de confirmation mot de passe ne correspond pas.')
                ->type('[name="password"]', 'G0¤d5tr@ñ9P##55wo&d')
                ->type('[name="password_confirmation"]', 'G0¤d5tr@ñ9P##55wo&d')
                ->check('[type="checkbox"]')
                ->press("S'INSCRIRE")
                ->waitForText('Tableau de bord', 8)
                ->assertSee('David Bowie')
                ->assertSee("Tout d'abord, veuillez enregistrer votre site web ou serveur et lui donner un nom.")
                ->press('David Bowie')
                ->clickLink('Se déconnecter')
                ->waitForText('Compte', 8)
                ->clickLink('Compte')
                ->waitForText('SE CONNECTER', 8)
                ->assertFocused('[name="email"]')
                ->type('[name="email"]', 'ziggy@star.dust')
                ->keys('[name="email"]', '{tab}')
                ->assertFocused('[name="password"]')
                ->type('[name="password"]', 'G0¤d5tr@ñ9P##55wo&d')
                ->keys('[name="password"]', '{tab}')
                ->press('SE CONNECTER')
                ->waitForText('Tableau de bord', 8)
                ->assertSee('David Bowie')
                ->assertSee("Tout d'abord, veuillez enregistrer votre site web ou serveur et lui donner un nom.")
                ->assertFocused('[name="name"]')
                ->type('[name="name"]', 'Heroes')
                ->keys('[name="name"]', '{tab}')
                ->assertFocused('[name="type"][value="domain"]')
                ->keys('[name="type"][value="domain"]', '{tab}')
                ->assertFocused('[name="domain"]')
                ->type('[name="domain"]', 'verify.selfbuild.fr')
                ->press("AJOUTER L'AUTORISATION")
                ->waitForText('VÉRIFIER', 8)
                ->assertSee('Heroes')
                ->assertDontSee('Payantes*')
                ->assertSee('Avant que vous puissiez commencer à utiliser "verify.selfbuild.fr", nous devons vérifier que vous en êtes le propriétaire.')
                ->assertSee('Veuillez télécharger le fichier ')
                ->assertSee(".html et le publier pour le rendre accessible à l'URL http://verify.selfbuild.fr/.well-known/")
                ->clickLink('Vérifier')
                ->assertSee('Payantes*')
                ->assertSee('Requêtes pour '.now()->locale('fr')->monthName)
                ->press('[title="Supprimer"]')
                ->acceptDialog()
                ->waitForText('Tableau de bord', 8)
                ->assertSee("Tout d'abord, veuillez enregistrer votre site web ou serveur et lui donner un nom.");
        });
    }
}
