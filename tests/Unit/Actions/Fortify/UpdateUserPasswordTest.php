<?php

namespace Tests\Unit\Actions\Fortify;

use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class UpdateUserPasswordTest extends TestCase
{
    public function testUpdate(): void
    {
        $ziggy = $this->newZiggy();
        (new UpdateUserPassword())->update($ziggy, [
            'current_password'      => 'G0¤d5tr@ñ9P##55wo&d',
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
            (new UpdateUserPassword())->update($ziggy, [
                'current_password'      => 'BadG0¤d5tr@ñ9P##55wo&d',
                'password'              => 'Weak',
                'password_confirmation' => 'Weak',
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        $this->assertSame([
            'password' => ['Le champ mot de passe doit avoir au moins 8 caractères.'],
            'current_password' => ['Le mot de passe fourni ne correspond pas à votre mot de passe actuel.'],
        ], $errors);
    }
}
