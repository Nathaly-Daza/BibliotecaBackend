<?php

namespace App\Http\Controllers;

use App\Models\BibliotecaInscription;
use App\Models\Service;
use Illuminate\Http\Request;

// Controlador que maneja operaciones CRUD para inscripciones de biblioteca
class BibliotecaInscriptionController extends Controller
{
    // Función para traer todas las inscripciones que existen en la base de datos
    public function index($proj_id, $use_id)
    {
        // Dirige a la función del modelo con el nombre 'select'.
        $bilioteca = BibliotecaInscription::select();
        // Si no hay ninguna inscripción en la base de datos, se retorna un mensaje de error.
        if ($bilioteca == null) {
            return response()->json([
                'status' => False,
                'message' => 'No hay inscripciones disponibles.'
            ], 400);
            // Al encontrar inscripciones el sistema devuelve los datos en forma de respuesta api.
        } else {
            Controller::NewRegisterTrigger("Se realizó una busqueda de datos en la tabla biblioteca_Inscriptions ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $bilioteca
            ], 200);
        }

    }

    // Método para almacenar una nueva inscripción en la biblioteca
    public function store(Request $request, $proj_id, $use_id)
    {
        return BibliotecaInscription::make($request, $proj_id, $use_id);
    }

    // Método para mostrar detalles de una inscripción específica
    public function show($proj_id, $use_id, $id)
    {
        // Busca la inscripción por su ID
        $bilioteca = BibliotecaInscription::findOne($id);

        // Si la inscripción no existe, devuelve un mensaje de error
        if ($bilioteca == null) {
            return response()->json([
                'status' => False,
                'message' => 'La inscripción no existe.'
            ], 400);
        } else {
            // Si encuentra la inscripción, registra un evento y devuelve los datos
            Controller::NewRegisterTrigger("Se realizó una busqueda de datos en la tabla biblioteca_Inscriptions ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $bilioteca
            ], 200);
        }
    }

    // Método para actualizar una inscripción existente en la biblioteca
    public function update(Request $request, $proj_id, $use_id, $id)
    {
        return BibliotecaInscription::Amend($request, $proj_id, $use_id, $id);

    }

    // Método para desactivar o activar una inscripción de la biblioteca
    public function destroy(Request $request, $proj_id, $use_id, $id)
    {
        // Busca la inscripción por su ID

        $desactivate = bibliotecaInscription::find($id);

        // Verifica si hay cuotas disponibles o si la inscripción está activa
        $quotas = Service::substractQuote($request);
        if ($quotas == true || $desactivate->bio_ins_status == 1) {
            // Cambia el estado de la inscripción (activa o desactivada)
            ($desactivate->bio_ins_status == 1)
                ? $desactivate->bio_ins_status = 0
                : $desactivate->bio_ins_status = 1;

            // Guarda los cambios en la base de datos
            $desactivate->save();

            // Prepara el mensaje de éxito según el estado cambiado
            $message = ($desactivate->bio_ins_status == 1) ? 'Activado' : 'Desactivado';
            // Registra un evento con información relevante

            Controller::NewRegisterTrigger("Se cambio el estado de una inscripción en la tabla biblioteca_inscriptions ", 2, $proj_id, $use_id);

            // Devuelve una respuesta JSON con el resultado y los datos de la inscripción
            return response()->json([
                'status' => True,
                'message' => '' . $message . ' exitosamente.',
                'data' => $desactivate
            ], 200);
        } else {

            // Si no se pueden activar los cupos, devuelve un mensaje de error
            return response()->json([
                'status' => False,
                'message' => 'No se puede activar la inscripción, ya que no hay suficientes cupos disponibles.'
            ]);
        }
    }

    // Método para buscar inscripciones activas de un estudiante en la biblioteca
    public static function actives($proj_id, $use_id, $id)
    {
        // Busca las inscripciones activas del estudiante por su ID
        $bilioteca = BibliotecaInscription::studentActive($id);

        // Si no encuentra inscripciones activas, devuelve un mensaje de error
        if ($bilioteca == '[]') {
            return response()->json([
                'status' => False,
                'message' => 'La inscripción no existe.'
            ], 400);
        } else {

            // Si encuentra inscripciones activas, registra un evento y devuelve los datos
            Controller::NewRegisterTrigger("Se realizó una busqueda de datos en la tabla biblioteca_Inscriptions ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $bilioteca
            ], 200);
        }
    }
}
