<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'use_id';
    protected $fillable = [
        'use_mail',
        'use_password',
        'use_status'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public static function UserAcces($use_mail){
        $access = User::select('users.*', 'access.proj_id')
                    ->join('access', 'users.use_id', '=', 'access.use_id')
                    ->where('use_mail', '=', $use_mail)->get();



    return $access;
    }
    // Método para obtener los proyectos 1 y 7 que los usuarios tienen acceso
    public static function getUsersWithAccessToProjects()
    {
        return self::select('users.use_id', 'users.use_mail', 'users.use_status', 'access.proj_id', 'access.acc_status')
            ->join('access', 'users.use_id', '=', 'access.use_id')
            ->whereIn('access.proj_id', [1, 7]) // Proyectos específicos
            ->where('access.acc_status', 1) // Validar que el acceso está activo
            ->get();
    }


   /* public function getUsersForProjects(): JsonResponse
    {
        $users = User::getUsersWithAccessToProjects();
        return response()->json($users);
    }*/
}
