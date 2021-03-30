<?php

namespace Tests\Unit\Console\Commands;

use App\Mail\Recovery;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

final class MailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function testUnknownContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown content doNotExist');

        $this
            ->artisan('mail', ['content' => 'doNotExist', 'users' => '1,2,3'])
            ->expectsOutput('xx');
    }

    public function testRecoveryMail(): void
    {
        $secretFile = __DIR__ . '/../../../../secret.php';
        $secretFileContent = file_exists($secretFile) ? file_get_contents($secretFile) : null;
        $ziggy = $this->newZiggy();
        $ziggy->apiAuthorizations()->create([
            'name'  => 'Music',
            'type'  => 'domain',
            'value' => 'music.github.io',
        ]);
        file_put_contents(
            $secretFile,
            '<?php return ' . var_export(['ziggy@star.dust' => 'foobar'], true) . ';',
        );

        $this->artisan('mail', ['content' => 'recovery', 'users' => (string) $ziggy->id]);

        Mail::assertSent(Recovery::class, static function (Recovery $mail) {
            return $mail->hasTo(config('app.super_admin'))
                && $mail->viewData['name'] === 'David Bowie'
                && $mail->viewData['password'] === 'foobar'
                && $mail->viewData['plan'] === 'Vicopo Free'
                && iterator_to_array($mail->viewData['properties']) === ['music.github.io'];
        });

        $this->artisan('mail', ['content' => 'recovery', 'users' => (string) $ziggy->id, '--confirm' => true]);

        Mail::assertSent(Recovery::class, static function (Recovery $mail) {
            return $mail->hasTo('ziggy@star.dust')
                && $mail->viewData['name'] === 'David Bowie'
                && $mail->viewData['password'] === 'foobar'
                && $mail->viewData['plan'] === 'Vicopo Free'
                && iterator_to_array($mail->viewData['properties']) === ['music.github.io'];
        });

        if ($secretFileContent !== null) {
            file_put_contents($secretFile, $secretFileContent);

            return;
        }

        unlink($secretFile);
    }
}
