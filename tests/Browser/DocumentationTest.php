<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class DocumentationTest extends DuskTestCase
{
    public function testDocumentationMainDemo(): void
    {
        $this->browse(static function (Browser $browser) {
            $browser
                ->visit('/')
                ->assertSee('VICOPO')
                ->waitFor('#code', 2)
                ->type('#code', '680')
                ->waitFor('#output a', 2)
                ->assertSee('COLMAR')
                ->click('#output a')
                ->assertInputValue('#city', 'COLMAR');
        });
    }
}
