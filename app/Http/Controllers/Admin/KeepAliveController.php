<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PersistentLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class KeepAliveController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (Auth::check()) {
            PersistentLogin::queue(Auth::user());
        }

        return response()->json([
            'ok' => true,
            'token' => csrf_token(),
        ]);
    }
}
