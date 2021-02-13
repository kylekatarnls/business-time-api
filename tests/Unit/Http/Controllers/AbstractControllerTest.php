<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AbstractController;
use App\Models\ApiAuthorization;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class AbstractControllerTest extends TestCase
{
    public function testGetUser(): void
    {
        Auth::login($this->newZiggy());
        $controller = new class () extends AbstractController {
            public function getUserName(): string
            {
                return $this->getUser()->name;
            }
        };

        $this->assertSame('David Bowie', $controller->getUserName());
    }

    public function testGetApiAuthorizations(): void
    {
        $ziggy = $this->newZiggy();
        $ziggy->apiAuthorizations()->create([
            'name' => 'Music',
            'type' => 'domain',
            'value' => 'music.github.io',
        ]);
        Auth::login($ziggy);
        $controller = new class () extends AbstractController {
            public function getAuthValues(): array
            {
                return $this->getApiAuthorizations()->map(
                    static fn (ApiAuthorization $auth) => $auth->value,
                )->toArray();
            }
        };

        $this->assertSame(['music.github.io'], $controller->getAuthValues());
    }

    public function testClearCache(): void
    {
        $file = __DIR__ . '/../../../../data/properties/domain/music.github.io.php';
        touch($file);
        $ziggy = $this->newZiggy();
        $ziggy->apiAuthorizations()->create([
            'name' => 'Music',
            'type' => 'domain',
            'value' => 'music.github.io',
        ]);
        Auth::login($ziggy);
        $controller = new class () extends AbstractController {
            public function clear(): bool
            {
                return $this->clearCache('domain', 'music.github.io');
            }
        };

        $this->assertFileExists($file);
        $this->assertTrue($controller->clear());
        $this->assertFileDoesNotExist($file);
    }
}
