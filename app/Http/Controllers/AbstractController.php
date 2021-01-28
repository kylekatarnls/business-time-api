<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AbstractController extends Controller
{
    protected function getUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    protected function getApiAuthorizations(?User $user = null): Collection
    {
        return ($user ?? $this->getUser())->apiAuthorizations()->getResults();
    }

    protected function clearCache(string $type, string $value): bool
    {
        return @unlink(__DIR__ . "/../../../data/properties/$type/$value.php") ?: false;
    }

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
