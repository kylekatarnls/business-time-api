<?php

namespace Tests\Unit\Mail;

use App\Mail\PlanChange;
use Tests\TestCase;

final class PlanChangeTest extends TestCase
{
    public function testPlanChange(): void
    {
        $mail = new PlanChange([
            'content' => 'My message',
        ]);

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/[\n\r]/', trim(strip_tags($mail->build()->render())))),
        ));

        $this->assertSame([
            'Abonnement activé avec succès !',
            'Vicopo',
            'My message',
        ], $lines);

        $mail = new PlanChange([
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
            'Abonnement activé avec succès !',
            'Vicopo',
            '{',
            '&quot;foo&quot;: &quot;bar&quot;,',
            '&quot;biz&quot;: 9',
            '}',
            'My message',
        ], $lines);
    }
}
