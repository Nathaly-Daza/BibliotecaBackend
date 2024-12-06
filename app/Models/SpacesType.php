<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpacesType extends Model
{
    use HasFactory;

    protected $primaryKey = 'spa_typ_id';

    protected $fillable = ['spa_typ_name'];

}
