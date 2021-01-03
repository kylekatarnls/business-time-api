<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AbstractController extends Controller
{
    protected function getUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    protected function getApiAuthorizations(): Collection
    {
        return $this->getUser()->apiAuthorizations()->getResults();
    }

    protected function clearCache(string $type, string $value): bool
    {
        return @unlink(__DIR__ . "/../../../data/properties/$type/$value.php") ?: false;
    }
}
