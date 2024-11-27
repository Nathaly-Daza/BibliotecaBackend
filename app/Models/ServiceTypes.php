<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceTypes extends Model
{
    use HasFactory;

    protected $primaryKey ='ser_typ_id';

    protected $fillable = [
      'ser_typ_name',
      'ser_typ_status'
    ];

    public $timestamps = false;
}
