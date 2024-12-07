<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Http\Request;
class AuthController extends Controller
{
    public function login(Request $request){


        $access= User::UserAcces($request->use_mail);
        // Buscar el usuario por correo electrónico
        $user = DB::table('users')->where('use_mail', '=', $request->use_mail)->first();

        // Obtener los IDs de proyectos a los que tiene acceso el usuario
        $projectIds = DB::table('access')
        ->join('users', 'access.use_id', '=', 'users.use_id')
        ->where('users.use_mail', $request->use_mail)
        ->whereIn('access.proj_id', [1, 7]) // Filtra solo acc_id 1 o 7
        ->pluck('proj_id')
        ->toArray(); // Convertimos la colección en un arreglo plano

        // Definir el endpoint de la API dependiendo de los proyectos a los que tiene acceso el usuario
        if (in_array(1, $projectIds)) {
            $endpoint = 'http://127.0.0.1:8088/api/login/1';
        } elseif (in_array(7, $projectIds)) {
            $endpoint = 'http://127.0.0.1:8088/api/login/7';
        } else{
            // Manejar el caso en que el usuario no tenga acceso a los proyectos 1 o 7
            return response()->json([
                'status' => false,
                'message' => 'El usuario no tiene acceso a los proyectos requeridos.'
            ], 403);
        }

        $response = Http::post($endpoint, [
            "use_mail" => $request->use_mail,
            "use_password" => $request->use_password
        ]);

       

        



        // Check if the HTTP request was successful
        if ($response->successful()) {
            // Get the token from the JSON response if present
            $responseData = $response->json();
            $token = isset($responseData['token']) ? $responseData['token'] : null;
            
            if ($token !== null) {



                $user = User::find($user->use_id);
                Auth::login($user);



                return response()->json([
                    'status' => true,
                    'data' => [
                        // "message" => $responseData['message'],
                        "token" => $token,
                        "use_id" => $user->use_id,
                        "token_id" => $responseData['token_id'],
                        "proj_id" => $projectIds,
                        "acc_administrator" => $responseData['acc_administrator'],
                        'per_document' => $responseData['per_document'] ]
                ],200);
            } else {
                // Handle the case where 'token' is not present in the response
                return response()->json([
                    'status' => false,
                    'message' => $response->json()
                ],401);
            }
        } else {
            // Handle the case where the HTTP request was not successful
            return response()->json([
                'status' => false,
                'message' => $response->json()['message']
            ],400);
        }
    }

    // Método para cerrar sesión
    public function logout(Request $request)
    {
        $id = $request->input('use_id');
        $token_id = $request->input('token_id');
        // Eliminar todos los tokens de acceso del usuario
        $tokens = DB::table('personal_access_tokens')->where('tokenable_id', '=', $id)->where('id', '=', $token_id)->delete();
        return response()->json([
            'status' => true,
            'message' => "logout success."
        ]);
    }
}
