<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

final class MailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function testUnknownContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown content doNotExist');

        $this
            ->artisan('mail', ['content' => 'doNotExist', 'users' => '1,2,3'])
            ->expectsOutput('xx');
    }
}
