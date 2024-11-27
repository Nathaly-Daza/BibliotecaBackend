<?php

namespace App\Http\Controllers;

use App\Models\Profesional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Controlador para manejar operaciones relacionadas con los profesionales
class ProfesionalsController extends Controller
{

    // Método para obtener todos los profesionales
    public function index($proj_id, $use_id)
    {
        // Obtiene todos los registros de profesionales
        $profesional = Profesional::all();

        // Si no hay profesionales disponibles, devuelve un mensaje de error
        if ($profesional == null) {
            return response()->json([
                'status' => False,
                'message' => 'There is no profesionals availables.'
            ], 400);
        } else {
            // Registra un evento de búsqueda en la tabla de profesionales

            Controller::NewRegisterTrigger("Se realizó una busqueda de datos en la tabla profesionals ", 4, $proj_id, $use_id);

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

        // Verifica si el usuario tiene permisos de administrador
        if ($request->acc_administrator == 1) {
            // Se establecen los parametros para ingresar datos.
            $rules = [
                'prof_name' => ['required', 'unique:profesionals', 'regex:/^[a-zA-ZÁÉÍÓÚÜÑ\s]+$/']
            ];
            // El sistema valida que estos datos sean correctos
            $validator = Validator::make($request->input(), $rules);

            // Si la validación falla, devuelve un mensaje de error
            if ($validator->fails()) {
                return response()->json([
                    'status' => False,
                    'message' => $validator->errors()->all()
                ], 400);
            } else {

                // Crea una nueva instancia de Profesional y guarda los datos
                $profesional = new Profesional($request->input());
                $profesional->prof_name = $request->prof_name;
                $profesional->prof_status = 1;
                $profesional->save();
                // Se guarda la novedad en la base de datos.
                Controller::NewRegisterTrigger("Se realizó una inserción de un dato en la tabla profesionals ", 3, $proj_id, $use_id);

                // Devuelve un JSON con el estado, mensaje de éxito y datos del profesional creado
                return response()->json([
                    'status' => True,
                    'message' => 'Profesional ' . $profesional->prof_name . ' created successfully',
                    'data' => $profesional
                ], 200);
            }
        } else {

            // Devuelve un mensaje de acceso denegado si el usuario no es administrador
            return response()->json([
                'status' => False,
                'message' => 'Access denied. This action can only be performed by active administrators.'
            ], 403);
        }
    }

    // Método para mostrar un profesional específico por su ID
    public function show($proj_id, $use_id, $id)
    {

        // Busca el profesional por su ID
        $profesional = Profesional::find($id);

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

        // Verifica si el usuario tiene permisos de administrador
        if ($request->acc_administrator == 1) {
            // Se establecen los parametros para ingresar datos.
            $rules = [
                'prof_name' => ['required', 'unique:profesionals', 'regex:/^[a-zA-ZÁÉÍÓÚÜÑ\s]+$/'],
            ];
            // El sistema valida que estos datos sean correctos
            $validator = Validator::make($request->input(), $rules);
            if ($validator->fails()) {
                return response()->json([
                    'status' => False,
                    'message' => $validator->errors()->all()
                ], 400);
            } else {

                // Busca el profesional por su ID y actualiza los datos
                $profesional = Profesional::find($id);
                $profesional->prof_name = $request->prof_name;
                $profesional->save();
                // Se guarda la novedad en la base de datos.
                Controller::NewRegisterTrigger("Se realizó una actualización en la información del dato " . $request->prof_name . " de la tabla profesionals ", 1, $proj_id, $use_id);
                return response()->json([
                    'status' => True,
                    'message' => 'space ' . $profesional->prof_name . ' modified successfully',
                    'data' => $profesional
                ], 200);
            }
        } else {

            // Devuelve un mensaje de acceso denegado si el usuario no es administrador
            return response()->json([
                'status' => False,
                'message' => 'Access denied. This action can only be performed by active administrators.'
            ], 403);
        }
    }

    // Método para cambiar el estado (activar o desactivar) de un profesional por su ID
    public function destroy(Request $request, $proj_id, $use_id, $id)
    {

        // Verifica si el usuario tiene permisos de administrador
        if ($request->acc_administrator == 1) {
            // Busca el profesional por su ID
            $desactivate = Profesional::find($id);

            // Cambia el estado del profesional (activo o inactivo)
            ($desactivate->prof_status == 1) ? $desactivate->prof_status = 0 : $desactivate->prof_status = 1;
            $desactivate->save();

            // Registra un evento de cambio de estado en la tabla de profesionales
            Controller::NewRegisterTrigger("Se cambio el estado de un dato en la tabla profesionals ", 2, $proj_id, $use_id);

            // Devuelve un JSON con el mensaje de éxito y los datos del profesional actualizado
            return response()->json([
                'message' => 'Status of ' . $desactivate->prof_status . ' changed successfully.',
                'data' => $desactivate
            ], 200);
        } else {
            // Devuelve un mensaje de acceso denegado si el usuario no es administrador
            return response()->json([
                'status' => False,
                'message' => 'Access denied. This action can only be performed by active administrators.'
            ]);
        }
    }

    // Método para obtener todos los profesionales (una variante)
    public function Profs($proj_id, $use_id)
    {
        // Obtiene todos los registros de profesionales (otra variante)
        $profesionals = Profesional::Profs();

        // Si no hay profesionales disponibles, devuelve un mensaje de error
        if ($profesionals == null) {
            return response()->json([
                'status' => False,
                'message' => 'This profesional does not exist.'
            ], 400);
        } else {
            // Registra un evento de búsqueda específica en la tabla de profesionales
            Controller::NewRegisterTrigger("Se realizó una busqueda de un dato específico en la tabla profesionals.", 4, $proj_id, $use_id);

            // Devuelve un JSON con el estado y los datos de los profesionales encontrados
            return response()->json([
                'status' => True,
                'data' => $profesionals
            ], 200);
        }
    }
}
