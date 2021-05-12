<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AuthorizationController;
use App\Models\ApiAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

final class AuthorizationControllerTest extends TestCase
{
    private $failApiAuthorizationSaving = false;

    protected function setUp(): void
    {
        parent::setUp();

        ApiAuthorization::saving(function () {
            return !$this->failApiAuthorizationSaving;
        });
    }

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

        $this->failApiAuthorizationSaving = true;
        $session->flush();
        $response = $controller->verify('domain', 'verify.selfbuild.fr');
        $this->failApiAuthorizationSaving = false;

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

    public function testVerifyIp(): void
    {
        $ip = '189.204.12.55';
        $request = new Request();
        $request->server->set('REMOTE_ADDR', $ip);
        $ziggy = $this->newZiggy();
        /** @var ApiAuthorization $authorization */
        $authorization = $ziggy->apiAuthorizations()->create([
            'name' => 'Ultimate central server',
            'type' => 'ip',
            'value' => $ip,
        ]);
        $token = $authorization->getVerificationToken();
        Auth::login($ziggy);
        $controller = new AuthorizationController();

        $response = $controller->verifyIp($request, $ziggy->email, $token, $ip);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());

        $response = $controller->verifyIp($request, 'foobar@foobar.com', $token, $ip);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            "Erreur\n" .
            "Le jeton $token n'est pas pour l'adresse IP : 189.204.12.55. " .
            "Veuillez accéder à cet URL depuis votre serveur avec l'IP 189.204.12.55.",
            $response->getContent()
        );

        $request = new Request();
        $request->server->set('REMOTE_ADDR', $ip);
        $request->headers->set(
            'Accept',
            'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,' .
            'application/signed-exchange;v=b3;q=0.9'
        );
        $response = $controller->verifyIp($request, $ziggy->email, $token, $ip);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'Adresse IP 189.204.12.55 confirmée pour votre serveur.',
            $response->getContent(),
        );

        $this->failApiAuthorizationSaving = true;
        $message = null;

        try {
            $controller->verifyIp($request, $ziggy->email, $token, $ip);
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        }

        $this->failApiAuthorizationSaving = false;

        $this->assertSame(
            "Une erreur inconnue s'est produite lors de la vérification, veuillez réessayer.",
            $message,
        );

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '1.2.3.4');
        $request->headers->set(
            'Accept',
            'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,' .
            'application/signed-exchange;v=b3;q=0.9'
        );
        $response = $controller->verifyIp($request, $ziggy->email, $token, $ip);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString(
            "Le jeton $token n&#039;est pas pour l&#039;adresse IP : 1.2.3.4. " .
            "Veuillez accéder à cet URL depuis votre serveur avec l&#039;IP $ip.",
            $response->getContent(),
        );
        $this->assertStringContainsString(
            'Adresse IP visible : 1.2.3.4',
            $response->getContent(),
        );
        $this->assertStringContainsString(
            'Adresse IP attendue : 189.204.12.55',
            $response->getContent(),
        );
        $this->assertStringContainsString(
            'Connectez-vous à votre serveur (en SSH par exemple) et appelez l&#039;URL en exécutant cette command :',
            $response->getContent(),
        );
        $appUrl = config('app.url');
        $this->assertStringContainsString(
            "curl -s $appUrl/verify-ip/ziggy%40star.dust/$token/189.204.12.55.html",
            $response->getContent(),
        );

        $request = new Request();
        Request::setTrustedProxies(['123.45.67.89', '87.65.43.21'], Request::HEADER_X_FORWARDED_FOR);
        $server = [
            'REMOTE_ADDR' => '123.45.67.89',
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1, 87.65.43.21, 88.88.88.88',
        ];
        $request->initialize([], [], [], [], [], $server);
        $response = $controller->verifyIp($request, $ziggy->email, $token, $ip);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            "Erreur\nLe jeton $token n'est pour aucune des adresses IP : 88.88.88.88, 127.0.0.1. Veuillez accéder à cet URL depuis votre serveur avec l'IP $ip.",
            $response->getContent(),
        );

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '1.2.3.4');
        $request->headers->set('Accept', '*/*');
        $response = $controller->verifyIp($request, $ziggy->email, $token);
        $this->assertSame(
            "Erreur\nLe jeton $token n'est pas pour l'adresse IP : 1.2.3.4. Veuillez accéder à cet URL depuis votre serveur avec l'IP que vous souhaitez vérifier.",
            $response->getContent(),
        );

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '1.2.3.4');
        $request->headers->set('Accept', 'text/html');
        $response = $controller->verifyIp($request, $ziggy->email, $token);
        $this->assertStringContainsString(
            "Le jeton $token n&#039;est pas pour l&#039;adresse IP : 1.2.3.4. Veuillez accéder à cet URL depuis votre serveur avec l&#039;IP que vous souhaitez vérifier.",
            $response->getContent(),
        );
    }
}
