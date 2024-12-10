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

        // Dividir el token en dos partes usando el separador '|'
        $parts = explode('|', $token);

        // Verificar si hay una segunda parte
        if (count($parts) > 1) {
            $token = $parts[1]; // Obtener la parte después del '|'
        } else {
            return response()->json(['message' => 'Invalid token format'], 401);
        }

        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        $tokenHash = hash('sha256', $token);
        $personalAccessToken = DB::table('personal_access_tokens')->where('token', $tokenHash)->first();
        if (!$personalAccessToken) {
            return response()->json(['message' => 'Token not found'], 401);
        }

        if ($personalAccessToken->expires_at && Carbon::parse($personalAccessToken->expires_at)->isPast()) {
            // Actualizar la fecha de expiración a 2 minutos más
            return redirect()->route('refresh.token');
        }

        return $next($request);
    }
}
