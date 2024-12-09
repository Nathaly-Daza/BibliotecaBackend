<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckTokenExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        $tokenHash = hash('sha256', $token);
        $personalAccessToken = DB::table('personal_access_tokens')->where('token', $tokenHash)->first();

        if (!$personalAccessToken) {
            return response()->json(['message' => 'Token not found'], 401);
        }

        if ($personalAccessToken->expires_at && Carbon::parse($personalAccessToken->expires_at)->isPast()) {
            // Redirigir a la ruta de refresco del token
            return redirect()->route('refresh.token');
        }

        return $next($request);
    }
}
