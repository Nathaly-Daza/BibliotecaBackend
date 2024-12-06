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


        $response = Http::post('http://127.0.0.1:8088/api/login/1', [
            "use_mail" => $request->use_mail,
            "use_password" => $request->use_password
        ]);

        // Buscar el usuario por correo electrónico
        $user = DB::table('users')->where('use_mail', '=', $request->use_mail)->first();

        // Obtener los IDs de proyectos a los que tiene acceso el usuario
        $projectIds = DB::table('access')
            ->join('users', 'access.use_id', '=', 'users.use_id')
            ->where('users.use_mail', $request->use_mail)
            ->pluck('proj_id')
            ->toArray(); // Convertimos la colección en un arreglo plano


        // Check if the HTTP request was successful
        if ($response->successful()) {
            // Get the token from the JSON response if present
            $responseData = $response->json();
            $token = isset($responseData['token']) ? $responseData['token'] : null;
           // return $request->use_mail;
            // Check if a token was retrieved before storing it
            if ($token !== null) {



                $user = User::find($user->use_id);
                Auth::login($user);
                // Start the session and store the token
                // session_start();
                // $_SESSION['api_token'] = $token;
                // $_SESSION['use_id'] = $user->use_id;
                // $_SESSION['acc_administrator'] = $responseData['acc_administrator'];



                return response()->json([
                    'status' => true,
                    'data' => [
                        // "message" => $responseData['message'],
                        "token" => $token,
                        "use_id" => $user->use_id,
                        "token_id" => $responseData['token_id'],
                        "proj_id" => $projectIds,
                        "acc_administrator" => $responseData['acc_administrator'],
                        'per_document' => $responseData['per_document']  ]
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
