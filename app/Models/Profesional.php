<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Profesional extends Model
{
    use HasFactory;

    protected $primaryKey = 'prof_id';

    protected $fillable = [
        'prof_name',
        'prof_status'
    ];

    public $timestamps = false;

    public static function Profs(){

        return DB::table('profesionals')->select('prof_id','prof_name')->where('prof_status','=',1)->get();
    }



}
