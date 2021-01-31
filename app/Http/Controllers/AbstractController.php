<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Util\SendMail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AbstractController extends Controller
{
    use SendMail;

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
}
