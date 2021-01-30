<?php

namespace Tests\Unit\Actions\Fortify;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
}
