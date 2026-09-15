<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserPermissions
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
        if (!$user->hasAnyPermission($params)) {
            abort(404, 'Halaman Tidak Ditemukan!');
//            abort(403, 'Anda tidak memiliki izin untuk melakukan fungsi ini.');
        }

        return $next($request);
    }
}
