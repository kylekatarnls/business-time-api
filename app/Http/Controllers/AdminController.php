<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class AdminController extends AbstractController
{
    public function users(): View
    {
        return view('admin.users', [
            'users' => User::all(),
        ]);
    }

    public function user(int $id): RedirectResponse
    {
        Auth::loginUsingId($id);

        return redirect(route('dashboard'));
    }

    public function errors(): RedirectResponse
    {
        return redirect('/admin-panel/log-viewer');
    }
}
