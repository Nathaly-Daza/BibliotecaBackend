<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServicesController extends Controller
{

    // Método para obtener todos los servicios
    public function index($proj_id, $use_id)
    {
        // Ejecuta la función 'select' dentro del modelo de Service para traer todos los servicios
        $services = Service::Select();

        // Verificar si no hay servicios encontrados
        if ($services == null) {
            return response()->json([
                'status' => False,
                'message' => 'No se encontraron servicios'
            ], 400);
        } else {
            // Control de acciones
            Controller::NewRegisterTrigger("Se realizó una busqueda en la tabla services ", 4, $proj_id, $use_id);
            // devuelve los datos dentro de una respuesta JSON
            return response()->json([
                'status' => True,
                'data' => $services
            ], 200);
        }
    }

    // Método para almacenar un nuevo servicio
    public function store(Request $request, $proj_id, $use_id)
    {
        $rules = [
            'ser_name' => ['required', 'regex:/^([0-9A-ZÁÉÍÓÚÜÑ0-9\s¿?,!:-])+$/'],
            'ser_date' => ['required', 'regex:/^(\d{4})(\/|-)(0[1-9]|1[0-2])\2([0-2][0-9]|3[0-1])$/'],
            'ser_start' => ['required', 'regex:/^([0-1][0-9]|2[0-3])(:)([0-5][0-9])$/'],
            'ser_end' => ['required', 'regex:/^([0-1][0-9]|2[0-3])(:)([0-5][0-9])$/'],
            'ser_quotas' => ['required', 'regex:/^[1-9][0-9]?$|^100$/'],
            'ser_typ_id' => 'required|integer',
            'use_id' => 'required',


        ];
        $messages = [
            'ser_name.regex' => 'El nombre del servicio es invalido.',
            'ser_date.required' => 'La fecha del servicio es requerida.',
            'ser_date.regex' => 'El formato de la fecha del servicio no es valido.',
            'ser_quotas.regex' => 'El numero de cupos debe de ser mayor a 0 y menor 100.',
            'ser_quotas.required' => 'El numero de cupos es requerido.',
            'ser_start.required' => 'La hora inicial del servicio es requerida.',
            'ser_start.regex' => 'El formato de la hora inicial del servicio no es valido.',
            'ser_end.required' => 'La hora final del servicio es requerida.',
            'ser_end.regex' => 'El formato de la hora final del servicio no es valido.',
            'ser_typ_id.required' => 'El tipo de servicio es requerido.',
            'ser_typ_id.integer' => 'El tipo de servicio no es valido.',
            'use_id.required' => 'El profesional del servicio es requerido.',

        ];

        // Validar los datos recibidos según las reglas y mensajes prestablecidos
        $validator = Validator::make($request->input(), $rules, $messages);
        if ($validator->fails()) {
            // Retorna el error en forma de JSON
            return response()->json([
                'status' => False,
                'message' => $validator->errors()->all()
            ], 400);
        } else {
            // Llamar al método Store del modelo Service
            return Service::Store($proj_id, $use_id, $request);
        }
    }

    // Método para obtener un servicio por su ID
    public function show($proj_id, $use_id, $id)
    {
        $service = Service::FindOne($id); // Encontrar un servicio por su ID

        if ($service == null) // Verificar si no se encontró el servicio
        {
            return response()->json([
                'status' => False,
                'message' => 'No se encontraron servicios'
            ], 400);
        } else {

            // control de acciones
            Controller::NewRegisterTrigger("Se realizó la busqueda de un dato en la tabla services ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $service
            ], 200);
        }
    }

    // Método para actualizar un servicio por su ID
    public function update($proj_id, $use_id, Request $request, $id)
    {
        $rules = [
            'ser_name' => ['required', 'regex:/^([0-9A-ZÁÉÍÓÚÜÑ0-9\s¿?,!:-])+$/'],
            'ser_date' => ['required', 'regex:/^(\d{4})(\/|-)(0[1-9]|1[0-2])\2([0-2][0-9]|3[0-1])$/'],
            'ser_start' => ['required', 'regex:/^([0-1][0-9]|2[0-3])(:)([0-5][0-9])$/'],
            'ser_end' => ['required', 'regex:/^([0-1][0-9]|2[0-3])(:)([0-5][0-9])$/'],
            'ser_quotas' => ['required', 'regex:/^[1-9][0-9]?$|^100$/'],
            'ser_typ_id' => 'required|integer',
            'use_id' => 'required|integer',

        ];

        $messages = [
            'ser_name.regex' => 'El nombre del servicio es invalido.',
            'ser_date.required' => 'La fecha de la reserva es requerida.',
            'ser_quotas.regex' => 'El numero de cupos debe de ser mayor a 0 y menor 100.',
            'ser_quotas.required' => 'El numero de cupos es requerido.',
            'ser_date.regex' => 'El formato de la fecha de la reserva no es valido.',
            'ser_start.required' => 'La hora inicial de la reserva es requerida.',
            'ser_start.regex' => 'El formato de la hora inicial de la reserva no es valido.',
            'ser_end.required' => 'La hora final de la reserva es requerida.',
            'ser_end.regex' => 'El formato de la hora final de la reserva no es valido.',
            'ser_typ_id.required' => 'El tipo de reserva es requerido.',
            'ser_typ_id.integer' => 'El tipo de reserva no es valido.',
            'use_id.required' => 'El profesional a reservar es requerido.',


        ];

        // Validar los datos recibidos
        $validator = Validator::make($request->input(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'status' => False,
                'message' => $validator->errors()->all()
            ], 400);
        } else {
            // Llamar al método Amend del modelo Service
            return Service::Amend($proj_id, $use_id, $request, $id);
        }

    }

    // Método para desactivar o activar un servicio por su ID
    public function destroy($proj_id, $use_id, $id)
    {

        // Encontrar un servicio por su ID
        $desactivate = Service::find($id);
        ($desactivate->ser_status == 1) ? $desactivate->ser_status = 0 : $desactivate->ser_status = 1;

        // Guardar los cambios en el estado del servicio
        $desactivate->save();
        $message = ($desactivate->ser_status == 1) ? 'Activado' : 'Desactivado';

        // Registro de nueva acción
        Controller::NewRegisterTrigger("Se cambio el estado de un servicio en la tabla services ", 2, $proj_id, $use_id);
        return response()->json([
            'message' => '' . $message . ' exitosamente.',
            'data' => $desactivate
        ], 200);
    }

    // Método para filtrar servicios por columna y dato
    public function reserFilters($proj_id, $use_id, $column, $data)
    {

        // Filtrar servicios por columna y dato
        $services = Service::ReserFilters($column, $data);

        // Verificar si no se encontraron servicios
        if ($services == null) {
            return response()->json([
                'status' => False,
                'message' => 'No se han hecho reservaciones de servicios'
            ], 400);
        } else {
            // Control de acciones
            Controller::NewRegisterTrigger("Se realizó una busqueda en la tabla services ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $services
            ], 200);
        }
    }

    // Método para obtener los servicios activos de un usuario
    public function ActiveServiceUser($proj_id, $use_id)
    {

        // Obtener los servicios activos de un usuario
        $service = Service::ActiveServiceUser();
        // Verificar si no se encontraron servicios activos
        if ($service == '[]') {
            return response()->json([
                'status' => False,
                'message' => 'No se han hecho reservaciones de servicios'
            ], 400);
        } else {
            // Control de acciones
            Controller::NewRegisterTrigger("Se realizó una busqueda en la tabla services ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $service
            ], 200);
        }
    }

    public function calendar($proj_id, $use_id)
    {
        $services = Service::Calendar(); // Obtiene los servicios del calendario

        if ($services == null) { // Verifica si no se encontraron servicios
            return response()->json([
                'status' => False,
                'message' => 'No se han hecho reservaciones de servicios'
            ], 400);
        } else {
            // Registra la acción realizada
            Controller::NewRegisterTrigger("Se realizó una búsqueda en la tabla services ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $services
            ], 200);
        }
    }

    public function users(Request $request)
    {
        if ($request->acc_administrator == 1) { // Verifica si el usuario es administrador
            $users = Service::Users(); // Obtiene la lista de usuarios

            if ($users != null) { // Verifica si se encontraron usuarios
                return response()->json([
                    'status' => True,
                    'data' => $users
                ], 200);
            } else {
                return response()->json([
                    'status' => False,
                    'message' => 'No se han registrado usuarios'
                ], 400);
            }
        } else {
            return response()->json([
                'status' => False,
                'message' => 'Acceso denegado'
            ], 400);
        }
    }

    public function betweenDates($proj_id, $use_id, $startDate, $endDate)
    {
        $services = Service::betweenDates($startDate, $endDate); // Obtiene servicios entre las fechas

        if ($services == null) { // Verifica si no se encontraron servicios
            return response()->json([
                'status' => False,
                'message' => 'No se encontraron servicios'
            ], 400);
        } else {
            // Registra la acción realizada
            Controller::NewRegisterTrigger("Se realizó una búsqueda en la tabla services ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $services
            ], 200);
        }
    }


    public function usersIn($proj_id, $use_id, $id)
    {
        $services = Service::incriptionsPerService($id); // Obtiene los usuarios inscritos en un servicio

        if ($services == null) { // Verifica si no se encontraron servicios
            return response()->json([
                'status' => False,
                'message' => 'No se encontraron servicios'
            ], 400);
        } else {
            // Registra la acción realizada
            Controller::NewRegisterTrigger("Se realizó una búsqueda en la tabla services ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $services
            ], 200);
        }
    }

}

