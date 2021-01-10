<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            $property = $request->query->get('property');

            if ($property) {
                $request->session()->put('property', $property);
            }

            return route($request->cookie('vuid') ? 'login' : 'register');
        }
    }
}
