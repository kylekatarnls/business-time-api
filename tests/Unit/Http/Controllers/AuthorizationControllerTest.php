<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AuthorizationController;
use App\Models\ApiAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

final class AuthorizationControllerTest extends TestCase
{
    public function testCreate(): void
    {
        $ziggy = $this->newZiggy();
        Auth::login($ziggy);
        $controller = new AuthorizationController();

        $request = Request::create('/authorization', 'POST', [
            'name'   => 'Music',
            'type'   => 'domain',
            'domain' => '1.2.3.4',
        ]);

        $response = $controller->create($request);
        $session = $response->getSession();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(route('dashboard')));

        /** @var array[] $flash */
        $flash = $session?->get('_flash') ?? [];

        $this->assertIsArray($flash['new'] ?? null);
        $this->assertContains('_old_input', $flash['new']);
        $this->assertContains('authorisationsErrors', $flash['new']);

        /** @var array[] $flash */
        $flash = $session?->get('authorisationsErrors') ?? [];

        $this->assertSame('format', $flash['domain'] ?? null);

        $session->flush();
        $request = Request::create('/authorization', 'POST', [
            'name'   => 'Music',
            'type'   => 'domain',
            'domain' => 'music.github.io',
        ]);
        $response = $controller->create($request);
        $session = $response->getSession();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(route('dashboard')));

        /** @var array[] $flash */
        $flash = $session?->get('_flash') ?? [];

        $this->assertCount(0, $flash);
        $this->assertNull($session?->get('authorisationsErrors'));
        $this->assertSame(1, $ziggy->apiAuthorizations->where('value', 'music.github.io')->count());

        $session->flush();
        $request = Request::create('/authorization', 'POST', [
            'name'   => 'Other',
            'type'   => 'domain',
            'domain' => 'https://music.github.io/',
        ]);
        $response = $controller->create($request);
        $session = $response->getSession();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(route('dashboard')));

        /** @var array[] $flash */
        $flash = $session?->get('_flash') ?? [];

        $this->assertIsArray($flash['new'] ?? null);
        $this->assertContains('_old_input', $flash['new']);
        $this->assertContains('authorisationsErrors', $flash['new']);

        /** @var array[] $flash */
        $flash = $session?->get('authorisationsErrors') ?? [];

        $this->assertSame('duplicate', $flash['domain'] ?? null);
        $this->assertSame(1, $ziggy->apiAuthorizations->where('value', 'music.github.io')->count());

        ApiAuthorization::where([
            'user_id' => $ziggy->id,
            'value'   => 'music.github.io',
        ])->forceDelete();
    }

    public function testDelete(): void
    {
        $ziggy = $this->newZiggy();
        $ziggy->apiAuthorizations()->create([
            'name' => 'Ultimate central server',
            'type' => 'ip',
            'value' => '189.204.12.55',
        ]);
        Auth::login($ziggy);
        $controller = new AuthorizationController();

        $request = Request::create('/authorization', 'DELETE', [
            'type'  => 'ip',
            'value' => '189.204.12.55',
        ]);

        $response = $controller->delete($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($response->isRedirect(route('dashboard')));
        $this->assertSame(0, $ziggy->apiAuthorizations->where('value', '189.204.12.55')->count());
        $this->assertSame(1, ApiAuthorization::withTrashed()->where([
            'user_id' => $ziggy->id,
            'value'   => '189.204.12.55',
        ])->withTrashed()->count());

        ApiAuthorization::where([
            'user_id' => $ziggy->id,
            'value'   => '189.204.12.55',
        ])->forceDelete();
    }

    public function testGetVerifyToken(): void
    {
        $ziggy = $this->newZiggy();
        $authorization = $ziggy->apiAuthorizations()->create([
            'name' => 'Ultimate central server',
            'type' => 'ip',
            'value' => '189.204.12.55',
        ]);
        $tokenFile = __DIR__ . '/../../../../data/check/' . $authorization->id . '.txt';
        Auth::login($ziggy);
        $controller = new AuthorizationController();

        $this->assertFileDoesNotExist($tokenFile);

        $response = $controller->getVerifyToken('189.204.12.55');

        $this->assertFileExists($tokenFile);
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(file_get_contents($tokenFile), $response->getFile()->getContent());
    }

    public function testVerify(): void
    {
        $ziggy = $this->newZiggy();
        $authorization = $ziggy->apiAuthorizations()->create([
            'name' => 'Ultimate central server',
            'type' => 'ip',
            'value' => '189.204.12.55',
        ]);
        Auth::login($ziggy);
        $controller = new AuthorizationController();

        $response = $controller->verify('ip', '189.204.12.55');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $session = $response->getSession();
        $this->assertTrue($response->isRedirect(route('dashboard')));
        $this->assertSame(
            "L'IP doit être vérifiée par une requête envoyée depuis le serveur.",
            $session->get('verifyError'),
        );
        $this->assertSame($authorization->id, $session->get('verifiedAuthorization'));
        $this->assertSame(
            ['verifyError', 'verifiedAuthorization'],
            $session->get('_flash.new'),
        );

        $authorizationOk = $ziggy->apiAuthorizations()->create([
            'name' => 'Ultimate central server',
            'type' => 'domain',
            'value' => 'verify.selfbuild.fr',
        ]);

        $session->flush();
        $response = $controller->verify('domain', 'verify.selfbuild.fr');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $session = $response->getSession();
        $this->assertTrue($response->isRedirect(route('dashboard')));
        $this->assertNull($session->get('verifyError'));
        $this->assertSame($authorizationOk->id, $session->get('verifiedAuthorization'));
        $this->assertSame(
            ['verifyError', 'verifiedAuthorization'],
            $session->get('_flash.new'),
        );

        $authorizationKo = $ziggy->apiAuthorizations()->create([
            'name' => 'Ultimate central server',
            'type' => 'domain',
            'value' => 'not-verified.selfbuild.fr',
        ]);

        $session->flush();
        $response = $controller->verify('domain', 'not-verified.selfbuild.fr');
        $token = file_get_contents(
            __DIR__ . '/../../../../data/check/' . $authorizationKo->id . '.txt',
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $session = $response->getSession();
        $this->assertTrue($response->isRedirect(route('dashboard')));
        $this->assertSame(
            "L'URL http://not-verified.selfbuild.fr/.well-known/$token.html ne retourne pas \"$token\".",
            $session->get('verifyError'),
        );
        $this->assertSame($authorizationKo->id, $session->get('verifiedAuthorization'));
        $this->assertSame(
            ['verifyError', 'verifiedAuthorization'],
            $session->get('_flash.new'),
        );

        $fail = true;
        ApiAuthorization::saving(function () use (&$fail) {
            return !$fail;
        });

        $session->flush();
        $response = $controller->verify('domain', 'verify.selfbuild.fr');
        $fail = false;

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $session = $response->getSession();
        $this->assertTrue($response->isRedirect(route('dashboard')));
        $this->assertSame(
            "Une erreur inconnue s'est produite lors de la vérification, veuillez réessayer.",
            $session->get('verifyError'),
        );
        $this->assertSame($authorizationOk->id, $session->get('verifiedAuthorization'));
        $this->assertSame(
            ['verifyError', 'verifiedAuthorization'],
            $session->get('_flash.new'),
        );
    }
}
