<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Admin
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     *
     * @return mixed
     *
     * @throws AuthenticationException
     */
    public function handle($request, Closure $next)
    {
        if (!Auth::user()?->isSuperAdmin()) {
            throw new AuthenticationException(
                'Insufficient permissions.'
            );
        }

        return $next($request);
    }
}
