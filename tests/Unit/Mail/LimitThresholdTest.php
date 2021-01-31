<?php

namespace Tests\Unit\Mail;

use App\Mail\LimitThreshold;
use Tests\TestCase;

final class LimitThresholdTest extends TestCase
{
    public function testContact(): void
    {
        $mail = new LimitThreshold([
            'title' => 'Limit exceeded',
            'link' => '/foo',
            'content' => 'Quota over 80%',
        ]);

        $content = $mail->build()->render();
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/[\n\r]/', trim(strip_tags($content)))),
        ));

        $this->assertStringContainsString('href="/foo"', $content);
        $this->assertSame([
            'Limit exceeded',
            'Quota over 80%',
            'Augmenter la limite totale',
            'Vicopo',
        ], $lines);

        $mail = new LimitThreshold([
            'title' => 'Limit exceeded',
            'link' => '/foo',
            'content' => 'Quota over 80%',
            'properties' => [
                'foo' => 'bar',
                'biz' => 9,
            ],
        ]);

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/[\n\r]/', trim(strip_tags($mail->build()->render())))),
        ));

        $this->assertStringContainsString('href="/foo"', $content);
        $this->assertSame([
            'Limit exceeded',
            'Quota over 80%',
            'Augmenter la limite totale',
            'Vicopo',
            '{',
            '&quot;foo&quot;: &quot;bar&quot;,',
            '&quot;biz&quot;: 9',
            '}',
        ], $lines);
    }
}
