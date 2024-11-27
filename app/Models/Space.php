<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    use HasFactory;

    protected $primaryKey = 'spa_id';

    protected $fillable = [
        'spa_name',
        'spa_status'
    ];

    public $timestamps = false;
}
