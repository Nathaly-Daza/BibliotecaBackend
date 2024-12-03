<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckProjectAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int  $requiredProjId
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $requiredProjId)
    {
        $user = $request->user();
        $projId = $request->route('proj_id');

        // Obtener el estado de acceso del usuario al proyecto
        $access = DB::table('access')
            ->where('use_id', $user->id)
            ->where('proj_id', $projId)
            ->first();

        if (!$access || $access->acc_status == 0 || $projId != $requiredProjId) {
            return response()->json([
                'status' => false,
                'message' => 'Access denied.'
            ], 403);
        }

        return $next($request);
    }
}