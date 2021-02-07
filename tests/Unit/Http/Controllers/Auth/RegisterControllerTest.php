<?php

namespace Tests\Unit\Http\Controllers\Auth;

use App\Http\Controllers\Auth\RegisterController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;
use Tests\TestCase;

final class RegisterControllerTest extends TestCase
{
    public function testConstruct(): void
    {
        $controller = new RegisterController();

        $this->assertInstanceOf(RegisterController::class, $controller);
        $this->assertSame(
            ['guest'],
            collect($controller->getMiddleware())
                ->map(static fn (array $middleware) => $middleware['middleware'])
                ->toArray(),
        );
    }

    public function testValidator(): void
    {
        $data = [
            'name' => 'Albert',
        ];
        $controller = new class () extends RegisterController {
            public function callValidator(array $data)
            {
                return $this->validator($data);
            }
        };
        /** @var Validator $validator */
        $validator = $controller->callValidator($data);

        $this->assertInstanceOf(Validator::class, $validator);
        $this->assertSame($data, $validator->getData());
        $this->assertSame([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], $validator->getRules());
    }

    public function testCreate(): void
    {
        $this->removeUserByEmail('albert@selfbuild.fr');
        $data = [
            'name' => 'Albert',
            'email' => 'albert@selfbuild.fr',
            'password' => 'kB2çd,2jnbdèHb',
        ];
        $controller = new class () extends RegisterController {
            public function callCreate(array $data)
            {
                return $this->create($data);
            }
        };
        /** @var User $user */
        $user = $controller->callCreate($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Albert', $user->name);
        $this->assertSame('albert@selfbuild.fr', $user->email);
        $this->assertTrue(Hash::check('kB2çd,2jnbdèHb', $user->password));
    }
}
