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
    public function handle(Request $request, Closure $next)
    {
        
        $useMail = $request->input('use_mail');
        
        $user = DB::table('users')->where('use_mail', $useMail)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
                'access' => false,
            ], 404);
        }

        // Obtener los IDs de proyectos a los que tiene acceso el usuario
        $projectIds = DB::table('access')
            ->where('use_id', $user->use_id)
            ->pluck('proj_id')
            ->toArray();

        foreach ($projectIds as $projectId) {
            
            $accessStatus = DB::table('access')
                ->where('proj_id', $projectId)
                ->where('use_id', $user->use_id)
                ->value('acc_status');

            
            if (($projectId == 1 || $projectId == 7) && $accessStatus == 1) {
                return $next($request); // Continuar con la solicitud si tiene acceso
            }
        }
        
        
        return response()->json([
            'message' => 'Acceso denegado. El usuario no tiene acceso a los proyectos requeridos.',
            'access' => false,
        ], 403);
    }
}