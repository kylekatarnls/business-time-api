<?php

namespace Tests\Unit\Actions\Fortify;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CreateNewUserTest extends TestCase
{
    public function testCreate(): void
    {
        User::where('email', 'nobody@selfbuild.fr')->forceDelete();
        $creator = new CreateNewUser();
        $user = $creator->create([
            'name'                  => 'Nobody',
            'email'                 => 'nobody@selfbuild.fr',
            'password'              => 'IShould8e5tr¤ngEnough',
            'password_confirmation' => 'IShould8e5tr¤ngEnough',
        ]);
        User::where('email', 'nobody@selfbuild.fr')->forceDelete();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Nobody', $user->name);
        $this->assertSame('nobody@selfbuild.fr', $user->email);
        $this->assertTrue(Hash::check('IShould8e5tr¤ngEnough', $user->password));
    }

    public function testError(): void
    {
        $errors = [];

        try {
            $creator = new CreateNewUser();
            $user = $creator->create([
                'name'                  => 'Nobody',
                'email'                 => 'nobody@selfbuild.fr',
                'password'              => 'IShould8e5tr¤ngEnough',
                'password_confirmation' => 'IShould8eTheSame',
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        $this->assertSame([
            'password' => ['Le champ de confirmation mot de passe ne correspond pas.'],
        ], $errors);
    }
}
