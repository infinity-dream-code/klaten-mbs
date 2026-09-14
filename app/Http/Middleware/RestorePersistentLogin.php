<?php

namespace App\Http\Middleware;

use App\Support\PersistentLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestorePersistentLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            $user = PersistentLogin::userFromRequest($request);
            if ($user) {
                Auth::login($user, false);
            }
        }

        if (Auth::check() && !$request->routeIs('logout')) {
            PersistentLogin::queue(Auth::user());
        }

        return $next($request);
    }
}
