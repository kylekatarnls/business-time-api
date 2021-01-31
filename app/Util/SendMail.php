<?php

declare(strict_types=1);

namespace App\Util;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

trait SendMail
{
    protected function sendMail(string $email, Mailable $mail, array $adminData = []): void
    {
        $adminMail = clone $mail;
        Mail::to($email)->send($mail);
        Mail::to('kylekatarnls@gmail.com')->send($adminMail->with($adminData));
    }

    protected function sendMailSilently(string $email, Mailable $mail, array $adminData = []): void
    {
        try {
            $this->sendMail($email, $mail, $adminData);
        } catch (Throwable $exception) {
            Log::error($exception);
        }
    }
}
