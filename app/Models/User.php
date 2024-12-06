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
    
    public static function getUsersWithAccessToProjectBiblioteca()
    {
        return (
            User::select('users.use_id', 'users.use_mail', 'users.use_status', 'persons.per_name', 'persons.per_lastname')
                ->join('persons', 'users.use_id', '=', 'persons.use_id')
                ->join('access', 'users.use_id', '=', 'access.use_id')
                ->where('access.acc_status', 1)
                ->where('access.proj_id', 1)
                ->where('users.use_status', 1)
                ->groupBy('users.use_id', 'users.use_mail', 'users.use_status', 'persons.per_name', 'persons.per_lastname')
                ->get()
        );
    }


   /* public function getUsersForProjects(): JsonResponse
    {
        $users = User::getUsersWithAccessToProjects();
        return response()->json($users);
    }*/
}
