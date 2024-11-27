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
        $response = Http::post('http://10.10.1.123/fabrica_general/public/index.php/api/login/1', [
            "use_mail" => $request->use_mail,
            "use_password" => $request->use_password
        ]);
        // $user=DB::table('users')->where("use_mail",'=',$request->use_mail)->first();
        // // if($user == null){
        // //     return response()->json([
        // //         'status' => false,
        // //         'message' => "El usuario no existe."
        // //     ],400);
        // // }
        // $user = User::find($user->use_id);

        // Auth::login($user);

        // Check if the HTTP request was successful
        if ($response->successful()) {
            // Get the token from the JSON response if present
            $responseData = $response->json();
            $token = isset($responseData['token']) ? $responseData['token'] : null;
            // Check if a token was retrieved before storing it
            if ($token !== null) {

                $user=DB::table('users')->where("use_mail",'=',$request->use_mail)->first();
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

    public function logout(Request $id) {
        session_start();
        $tokens = DB::table('personal_access_tokens')->where('tokenable_id', '=', $id->use_id)->delete();
        session_destroy();
        return response()->json([
            'status'=> true,
            'message'=> "logout success."
        ],200);
    }
}
