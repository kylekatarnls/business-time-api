<?php

namespace Tests\Unit\Actions\Jetstream;

use App\Actions\Jetstream\DeleteUser;
use Carbon\Carbon;
use Carbon\Carbonite\Attribute\Freeze;
use Tests\TestCase;

final class DeleteUserTest extends TestCase
{
    #[Freeze('2021-01-30 16:20:31.365128')]
    public function testCreate(): void
    {
        $ziggy = $this->newZiggy();
        $before = $ziggy->deleted_at;
        (new DeleteUser())->delete($ziggy);
        $after = $ziggy->deleted_at;

        $this->assertNull($before);
        $this->assertInstanceOf(Carbon::class, $after);
        $this->assertSame('2021-01-30T16:20:31.365128Z', $after->toJSON());
    }
}
