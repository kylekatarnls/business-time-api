<?php

namespace Tests\Browser;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class ContactTest extends DuskTestCase
{
    public function testMessageSend(): void
    {
        $this->browse(function (Browser $browser) {
            $logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
            $contents = @file_get_contents($logFile) ?: '';
            file_put_contents($logFile, '');

            try {
                $browser
                    ->visit('/contact')
                    ->waitFor('#email', 5)
                    ->type('#email', 'bob@company.com')
                    ->type('#message', "Hello\nthere!")
                    ->click('button[type="submit"]')
                    ->waitFor('.message-sent-feedback', 5)
                    ->assertSee('Message envoyé.');
            } catch (\Exception $_) {
                $browser->dump();
                $browser
                    ->visit('/contact')
                    ->dump();
                exit;
            }

            $logs = str_replace("\r", '', trim(@file_get_contents($logFile) ?: ''));

            file_put_contents($logFile, $contents);

            $this->assertStringContainsString("Hello<br />\nthere!", $logs);
            $this->assertStringContainsString('Merci pour votre message, nous reviendrons rapidement vers vous.', $logs);
        });
    }
}
