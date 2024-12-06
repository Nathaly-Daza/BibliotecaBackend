<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpacesTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


    public function up()
    {
        Schema::create('spaces_types', function (Blueprint $table) {
            $table->id('spa_typ_id'); // Clave primaria con incremento automático
            $table->string('spa_typ_name', 50); // Nombre del tipo de espacio
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spaces_types');
    }
}
