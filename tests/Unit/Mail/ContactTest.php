<?php

namespace Tests\Unit\Mail;

use App\Mail\Contact;
use Tests\TestCase;

final class ContactTest extends TestCase
{
    public function testContact(): void
    {
        $mail = new Contact([
            'content' => 'My message',
        ]);

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/[\n\r]/', trim(strip_tags($mail->build()->render())))),
        ));

        $this->assertSame([
            'Merci pour votre message, nous reviendrons rapidement vers vous.',
            'Business-Time API',
            'My message',
        ], $lines);

        $mail = new Contact([
            'content'    => 'My message',
            'properties' => [
                'foo' => 'bar',
                'biz' => 9,
            ],
        ]);

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/[\n\r]/', trim(strip_tags($mail->build()->render())))),
        ));

        $this->assertSame([
            'Merci pour votre message, nous reviendrons rapidement vers vous.',
            'Business-Time API',
            '{',
            '&quot;foo&quot;: &quot;bar&quot;,',
            '&quot;biz&quot;: 9',
            '}',
            'My message',
        ], $lines);
    }
}
