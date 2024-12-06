<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Controlador para manejar operaciones relacionadas con los profesionales
class UsersController extends Controller

{

    // Método para obtener todos los profesionales
    public function index($proj_id, $use_id)
    {
        // Obtiene todos los registros de profesionales
        $profesional = User::getUsersWithAccessToProjectBiblioteca();

        // Si no hay profesionales disponibles, devuelve un mensaje de error
        if ($profesional == null) {
            return response()->json([
                'status' => False,
                'message' => 'There is no profesionals availables.'
            ], 400);
        } else {
            // Registra un evento de búsqueda en la tabla de profesionales

            Controller::NewRegisterTrigger("Se realizó una busqueda de datos en la tabla users ", 4, $proj_id, $use_id);

            // Devuelve un JSON con el estado, datos de los profesionales y código de estado 200 (OK)
            return response()->json([
                'status' => True,
                'data' => $profesional
            ], 200);
        }
    }

    // Método para almacenar un nuevo profesional
    public function store(Request $request, $proj_id, $use_id)
    {


    }

    // Método para mostrar un profesional específico por su ID
    public function show($proj_id, $use_id, $id)
    {

        // Busca el profesional por su ID
        $profesional = User::find($id);

        // Si el profesional no existe, devuelve un mensaje de error
        if ($profesional == null) {
            return response()->json([
                'status' => False,
                'message' => 'This profesional does not exist.'
            ], 400);
        } else {
            // Se guarda la novedad en la base de datos.
            Controller::NewRegisterTrigger("Se realizó una busqueda de un dato específico en la tabla profesionals.", 4, $proj_id, $use_id);

            // Devuelve un JSON con el estado y los datos del profesional encontrado
            return response()->json([
                'status' => True,
                'data' => $profesional
            ], 200);
        }
    }

    // Método para actualizar la información de un profesional por su ID
    public function update(Request $request, $proj_id, $use_id, $id)
    {

    }

    // Método para cambiar el estado (activar o desactivar) de un profesional por su ID
    public function destroy(Request $request, $proj_id, $use_id, $id)
    {
    }


}
