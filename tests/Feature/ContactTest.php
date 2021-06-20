<?php

namespace Tests\Feature;

use App\Mail\Contact;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class ContactTest extends TestCase
{
    public function testSendMessage(): void
    {
        $response = $this->get('/contact');

        $this->assertResponseStatus($response,200);
        $response->assertSeeText('Adresse e-mail');
        $response->assertSeeText('Message');
        $response->assertSeeText('Envoyer');

        $this->assertSame(1, preg_match('/<input([^>]*)name=["\']_token["\']([^>]*)>/', $response->content(), $token));
        $this->assertSame(1, preg_match('/value=["\']([^"\']+)["\']/', $token[1] . $token[2], $token));

        Mail::fake();

        $response = $this->post('/contact', [
            '_token' => $token[1],
            'email' => 'bob@company.com',
            'message' => "Hello\nthere!",
        ]);

        $response->assertRedirect('/contact');

        $response = $this->get('/contact');

        $response->assertSeeText('Message envoyé.');

        $this->assertBuiltMailSent(Contact::class, static fn (Contact $mail) => [
            $mail->hasTo('bob@company.com'),
            $mail->hasFrom('no-reply@selfbuild.fr', 'Business-Time API'),
            $mail->subject === 'Confirmation de message',
            $mail->viewData['content'] === "Hello\nthere!",
        ]);

        $this->assertBuiltMailSent(Contact::class, static fn (Contact $mail) => [
            $mail->hasTo('kylekatarnls@gmail.com'),
            $mail->hasFrom('no-reply@selfbuild.fr', 'Business-Time API'),
            $mail->subject === 'Confirmation de message',
            $mail->viewData['content'] === "bob@company.com\n\nHello\nthere!",
        ]);
    }

    private function assertBuiltMailSent(string $mailable, callable|int $callback): void
    {
        Mail::assertSent(
            $mailable,
            static fn (Contact $mail) => collect($callback($mail->build()))
                ->every(static fn ($result) => $result === true)
        );
    }
}
