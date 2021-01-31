<?php

namespace Tests\Unit\Util;

use App\Mail\LimitThreshold;
use App\Util\SendMail;
use Exception;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SendMailTest extends TestCase
{
    public function testSendMail(): void
    {
        $mailer = $this->getMailer();

        Mail::fake();

        $mailer->sendMail(
            'bob@selfbuild.fr',
            new LimitThreshold([
                'title' => 'Limit exceeded',
                'link' => '/foo',
                'content' => 'Quota over 80%',
            ]),
            [
                'properties' => [
                    'email' => 'bob@selfbuild.fr',
                ],
            ],
        );

        Mail::assertSent(LimitThreshold::class, static function (LimitThreshold $mail) {
            return $mail->hasTo('bob@selfbuild.fr')
                && $mail->viewData['title'] === 'Limit exceeded'
                && $mail->viewData['link'] === '/foo'
                && $mail->viewData['content'] === 'Quota over 80%'
                && !isset($mail->viewData['properties']);
        });

        Mail::assertSent(LimitThreshold::class, static function (LimitThreshold $mail) {
            return $mail->hasTo(config('app.super_admin'))
                && $mail->viewData['title'] === 'Limit exceeded'
                && $mail->viewData['link'] === '/foo'
                && $mail->viewData['content'] === 'Quota over 80%'
                && $mail->viewData['properties'] === [
                    'email' => 'bob@selfbuild.fr',
                ];
        });
    }

    public function testSendMailFailed(): void
    {
        $mailer = $this->getFailedMailer();

        Mail::fake();

        Log::shouldReceive('error')->with($mailer->exception);

        $mailer->sendMailSilently(
            'bob@selfbuild.fr',
            new LimitThreshold([
                'title' => 'Limit exceeded',
                'link' => '/foo',
                'content' => 'Quota over 80%',
            ]),
            [
                'properties' => [
                    'email' => 'bob@selfbuild.fr',
                ],
            ],
        );

        Mail::assertNothingSent();
    }

    private function getMailer(): object
    {
        return new class ()
        {
            use SendMail {
                sendMail as public;
            }
        };
    }

    private function getFailedMailer(): object
    {
        return new class ()
        {
            use SendMail {
                sendMailSilently as public;
            }

            public Exception $exception;

            public function __construct()
            {
                $this->exception = new Exception('Mail failure');
            }

            protected function sendMail(string $email, Mailable $mail, array $adminData = []): void
            {
                throw $this->exception;
            }
        };
    }
}
