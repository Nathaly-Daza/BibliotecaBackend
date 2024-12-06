<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureProjectAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $useId = $request->route('use_id');
        

        $user = DB::table('users')->where('use_id', $useId)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
                'access' => false,
            ], 404);
        }

        // Buscar los proyectos a los que el usuario tiene acceso
        $projectIds = DB::table('access')
            ->where('use_id', $user->use_id)
            ->pluck('proj_id')
            ->toArray(); // Obtener los proyectos a los que el usuario tiene acceso

        // Comprobar si el usuario tiene acceso a los proyectos 1 o 7 con acc_status = 1
        foreach ($projectIds as $projectId) {
            $accessStatus = DB::table('access')
                ->where('proj_id', $projectId)
                ->where('use_id', $user->use_id)
                ->value('acc_status');

            // Verificar si el proyecto es 1 o 7 y si el estado de acceso es 1
            if (($projectId == 1 || $projectId == 7) && $accessStatus == 1) {
                return $next($request);
            }
        }
        
        // Si el usuario no tiene acceso a los proyectos 1 o 7, denegar acceso
        return response()->json([
            'message' => 'Acceso denegado. El usuario no tiene acceso a los proyectos requeridos.',
            'access' => false,
        ], 403); 
    }
}
