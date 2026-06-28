<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordConfirmedIfEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Features::canManageTwoFactorAuthentication() ||
            Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword') === false) {
            return $next($request);
        }

        return app(RequirePassword::class)->handle($request, $next);
    }
}
