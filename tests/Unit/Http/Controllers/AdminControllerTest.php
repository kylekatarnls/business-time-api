<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AdminController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class AdminControllerTest extends TestCase
{
    public function testUsers(): void
    {
        $ziggy = $this->newZiggy();
        $controller = new AdminController();
        $view = $controller->users()->render();

        $this->assertStringContainsString('David Bowie', $view);
        $this->assertMatchesRegularExpression(
            '`<a[^>]+/admin-panel/user/' . $ziggy->id . '[^>]+>\s*Se connecter\s*</a>`',
            $view,
        );
    }

    public function testUser(): void
    {
        Auth::logout();
        $ziggy = $this->newZiggy();

        $this->assertNull(Auth::user());

        $controller = new AdminController();
        $response = $controller->user($ziggy->id);

        $this->assertSame($ziggy->id, Auth::user()->id);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(route('dashboard')));
    }

    public function testErrors(): void
    {
        $controller = new AdminController();
        $response = $controller->errors();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(config('app.url') . '/admin-panel/log-viewer'));
    }
}
