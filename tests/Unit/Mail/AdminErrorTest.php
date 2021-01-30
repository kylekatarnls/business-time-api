<?php

namespace Tests\Unit\Mail;

use App\Mail\AdminError;
use Exception;
use Tests\TestCase;

final class AdminErrorTest extends TestCase
{
    public function testAdminError(): void
    {
        $exceptions = [
            new Exception('One'),
            new Exception('Two'),
        ];
        $mail = new AdminError([
            'exceptions' => $exceptions,
        ]);

        $text = implode("\n", array_filter(
            array_map('trim', preg_split('/[\n\r]/', trim(strip_tags($mail->build()->render())))),
        ));

        $this->assertSame(implode("\n", array_map(
            static fn (Exception $exception) => htmlspecialchars(
                $exception->getMessage() . "\n" .
                $exception->getFile() . ':' . $exception->getLine() . "\n" .
                $exception->getTraceAsString()
            ),
            $exceptions,
        )), $text);
    }
}
