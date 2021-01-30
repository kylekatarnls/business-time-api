<?php

namespace Tests\Unit\Actions\Fortify;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ResetUserPasswordTest extends TestCase
{
    public function testReset(): void
    {
        $ziggy = $this->newZiggy();
        (new ResetUserPassword())->reset($ziggy, [
            'password'              => 'MyN3w@ne',
            'password_confirmation' => 'MyN3w@ne',
        ]);

        $this->assertTrue(Hash::check('MyN3w@ne', $ziggy->password));
    }

    public function testError(): void
    {
        $errors = [];

        try {
            $ziggy = $this->newZiggy();
            (new ResetUserPassword())->reset($ziggy, [
                'password'              => 'Weak',
                'password_confirmation' => 'Weak',
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        $this->assertSame([
            'password' => ['Le champ mot de passe doit avoir au moins 8 caractères.'],
        ], $errors);
    }
}
