<?php

namespace Tests\Browser;

use Carbon\Carbonite\Attribute\Freeze;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class PlanTest extends DuskTestCase
{
    #[Freeze('2020-12-15')]
    public function testPlanSubscription(): void
    {
        $this->browse(static function (Browser $browser) {
            $browser
                ->visit('/')
                ->assertSee('VICOPO');
        });
    }
}
