<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\Authenticate;
use ArrayObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class AuthenticateTest extends TestCase
{
    public function testRedirectTo(): void
    {
        $authenticate = new class (Auth::getFacadeRoot()) extends Authenticate
        {
            public function redirect(Request $request)
            {
                return $this->redirectTo($request);
            }
        };

        $request = new Request();
        $request->headers->set('Accept', 'application/json');

        $this->assertNull($authenticate->redirect($request));

        [$request, $store] = $this->getRequestWithSession();
        $response = $authenticate->redirect($request);
        $appUrl = config('app.url');

        $this->assertSame("$appUrl/register", $response);
        $this->assertFalse(isset($store['property']));

        [$request, $store] = $this->getRequestWithSession();
        $request->query->set('property', 'php.net');
        $request->cookies->set('vuid', 'ab123');
        $response = $authenticate->redirect($request);
        $appUrl = config('app.url');

        $this->assertSame("$appUrl/login", $response);
        $this->assertTrue(isset($store['property']));
        $this->assertSame('php.net', $store['property']);
    }

    private function getRequestWithSession(): array
    {
        $store = new ArrayObject();
        $request = new class ($store) extends Request {
            public function __construct(private ArrayObject $store)
            {
                parent::__construct();
            }

            public function session()
            {
                return new class ($this->store) {
                    public function __construct(private ArrayObject $store)
                    {
                        // Noop
                    }

                    public function put($key, $value): void
                    {
                        $this->store[$key] = $value;
                    }
                };
            }
        };

        return [$request, $store];
    }
}
