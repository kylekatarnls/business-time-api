<?php

namespace Tests\Feature;

use App\Mail\Contact;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    public function testSendMessage(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSeeText('Adresse e-mail');
        $response->assertSeeText('Mot de passe');
        $response->assertSeeText('Se souvenir de moi');
        $response->assertSeeText('Se connecter');
        $response->assertSeeText('Mot de passe oublié ?');
        $response->assertSeeText('Pas encore inscrit ?');

        $this->assertSame(1, preg_match('/<input([^>]*)name=["\']_token["\']([^>]*)>/', $response->content(), $token));
        $this->assertSame(1, preg_match('/value=["\']([^"\']+)["\']/', $token[1] . $token[2], $token));

        $response = $this->post('/login', [
            '_token' => $token[1],
            'email' => 'bob@company.com',
            'password' => "I'm not the correct password!",
        ]);

        $response->assertRedirect('/login');

        $response = $this->get('/login');

        $response->assertSeeText('Ces identifiants ne correspondent pas à nos enregistrements');
    }
}
