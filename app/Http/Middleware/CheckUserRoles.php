<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRoles
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$params)
    {
        if (!Auth::check()) {
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json(['retry' => true, 'token' => csrf_token()], 401);
            }

            return redirect('login');
        }
        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
            return $next($request);
        }
//        if (!$user->hasAnyRole($roles)) {
//            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
//        }
//
//        return $next($request);

        foreach ($params as $param) {
            if (method_exists($user, 'hasRole') && $user->hasRole($param)) {
                return $next($request);
            }
        }

        abort(404, 'Halaman Tidak Ditemukan!');
    }
}
