<?php

namespace Tests\Unit\Actions\Fortify;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Testing\File;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class UpdateUserProfileInformationTest extends TestCase
{
    public function testUpdate(): void
    {
        $ziggy = $this->newZiggy();
        (new UpdateUserProfileInformation())->update($ziggy, [
            'email' => 'ziggy@star.dust',
            'name'  => 'Halloween Jack',
        ]);

        $this->assertSame('Halloween Jack', $ziggy->name);
    }

    public function testError(): void
    {
        $errors = [];

        try {
            $ziggy = $this->newZiggy();
            (new UpdateUserProfileInformation())->update($ziggy, [
                'email' => 'ziggy@star.dust',
                'name'  => 'This name is way to long to be displayed properly anywhere' .
                    'This name is way to long to be displayed properly anywhere' .
                    'This name is way to long to be displayed properly anywhere' .
                    'This name is way to long to be displayed properly anywhere',
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        $this->assertSame([
            'name' => ['Le texte de nom ne peut contenir plus de 191 caractères.'],
        ], $errors);
    }

    public function testPhoto(): void
    {
        $ziggy = $this->newZiggy();
        $photo = __DIR__ . '/photo.jpg';

        (new UpdateUserProfileInformation())->update($ziggy, [
            'email' => 'ziggy@star.dust',
            'name'  => 'Halloween Jack',
            'photo' => new File('foobar.jpg', fopen($photo, 'r')),
        ]);

        $path = str_replace(config('app.url') . '/storage/', '', $ziggy->getProfilePhotoUrlAttribute());
        $file = storage_path('app/public/' . $path);

        $this->assertFileEquals($photo, $file);

        $ziggy->deleteProfilePhoto();

        $this->assertNull(User::find(['email' => 'ziggy@star.dust'])->first->getProfilePhotoUrlAttribute());

        unlink($file);
    }

    public function testEmail(): void
    {
        User::where(['email' => 'halloween.jack@selfbuild.fr'])->forceDelete();

        /** @var Router $router */
        $router = Route::getFacadeRoot();
        $routes = $router->getRoutes();
        $verifyRoute = $routes->getByName('verification.verify');
        $routes->add((new \Illuminate\Routing\Route(
            'GET',
            '/verification/verify/{id}/{hash}',
            function () {},
        ))->name('verification.verify'));

        $logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
        $contents = @file_get_contents($logFile) ?: '';
        file_put_contents($logFile, '');

        $ziggy = $this->newZiggy();
        $ziggy->email_verified_at = now();
        $ziggy->save();
        /** @var User $proxy */
        $proxy = new class ($ziggy) implements MustVerifyEmail {
            public bool $emailSent = false;

            public function __construct(private User $user)
            {
                // noop
            }

            public function __get(string $name)
            {
                return $this->user->$name;
            }

            public function __call(string $name, array $arguments)
            {
                return $this->user->$name(...$arguments);
            }

            public function hasVerifiedEmail()
            {
                return $this->user->hasVerifiedEmail();
            }

            public function markEmailAsVerified()
            {
                return $this->user->markEmailAsVerified();
            }

            public function sendEmailVerificationNotification()
            {
                $this->emailSent = true;
            }

            public function getEmailForVerification()
            {
                return $this->user->getEmailForVerification();
            }
        };

        try {
            (new UpdateUserProfileInformation())->update($proxy, [
                'email' => 'halloween.jack@selfbuild.fr',
                'name'  => 'Halloween Jack',
            ]);
        } catch (ValidationException $exception) {
            var_dump($exception->errors());
            exit;
        }

        $this->assertNull($ziggy->email_verified_at);

        file_put_contents($logFile, $contents);

        if ($verifyRoute) {
            $routes->add($verifyRoute);
        }

        User::where(['email' => 'halloween.jack@selfbuild.fr'])->forceDelete();

        $this->assertTrue($proxy->emailSent);
    }
}
