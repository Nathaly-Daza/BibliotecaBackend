<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReservationController extends Controller
{

    // Método para obtener todas las reservas
    public function index($proj_id, $use_id)
    {

        // Selecciona todas las reservas
        $reservations = Reservation::Select();

        // Si no hay reservas, devuelve un mensaje de error
        if ($reservations->isEmpty()) {
            return response()->json([
                'status' => False,
                'message' => 'No se encontraron reservas'
            ], 400);
        } else {
            // Registra un evento de búsqueda en la tabla de reservas
            Controller::NewRegisterTrigger("Se realizó una busqueda en la tabla reservations ", 4, $proj_id, $use_id);
            // Devuelve un JSON con el estado y los datos de las reservas encontradas

            return response()->json([
                'status' => True,
                'data' => $reservations
            ], 200);
        }
    }

    // Método para almacenar una nueva reserva
    public function store($proj_id, $use_id, Request $request)
    {
        // Reglas de validación
        $rules = [
            'res_date' => ['required', 'regex:/^(\d{4})(\/|-)(0[1-9]|1[0-2])\2([0-2][0-9]|3[0-1])$/'],
            'res_start' => ['required', 'regex:/^([0-1][0-9]|2[0-3])(:)([0-5][0-9])$/'],
            'res_end' => ['required', 'regex:/^([0-1][0-9]|2[0-3])(:)([0-5][0-9])$/'],
            'spa_id' => 'required|integer',
            'use_id' => 'required|integer',
            'isRecurring' => 'sometimes|boolean',
            'recurrenceType' => 'required_if:isRecurring,true|in:weekly,monthly',
            'recurrenceEndDate' => 'required_if:isRecurring,true|date|after_or_equal:res_date',
        ];
    
        $messages = [
            'recurrenceType.required_if' => 'El tipo de recurrencia es requerido si es una reserva recurrente.',
            'recurrenceEndDate.required_if' => 'La fecha de fin de recurrencia es requerida.',
        ];
    
        // Validación
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->all()
            ], 400);
        }
    
        // Verificar si es una reserva recurrente
        $isRecurring = $request->input('isRecurring', false);
        $recurrenceType = $request->input('recurrenceType');
        $recurrenceEndDate = $request->input('recurrenceEndDate');
    
        if ($isRecurring) {
            $currentDate = Carbon::create($request->res_date);
            $endDate = Carbon::create($recurrenceEndDate);
    
            while ($currentDate <= $endDate) {
                $existingReservation = Reservation::where('spa_id', $request->spa_id)
                    ->where('res_date', $currentDate->format('Y-m-d'))
                    ->where(function ($query) use ($request) {
                        $query->whereBetween('res_start', [$request->res_start, $request->res_end])
                              ->orWhereBetween('res_end', [$request->res_start, $request->res_end]);
                    })->exists();
    
                if (!$existingReservation) {
                    Reservation::create([
                        'res_date' => $currentDate->format('Y-m-d'),
                        'res_start' => $request->res_start,
                        'res_end' => $request->res_end,
                        'spa_id' => $request->spa_id,
                        'use_id' => $request->use_id,
                        'res_status' => 1,
                    ]);
                }
    
                // Incrementar la fecha según el tipo de recurrencia
                if ($recurrenceType === 'weekly') {
                    $currentDate->addWeek();
                } elseif ($recurrenceType === 'monthly') {
                    $currentDate->addMonth();
                }
            }
    
            return response()->json(['message' => 'Reservas recurrentes creadas exitosamente'], 201);
        }
    
        // Si no es recurrente, crear una sola reserva
        Reservation::create($request->all());
        return response()->json(['message' => 'Reserva creada exitosamente'], 201);
    }


    // Método para mostrar una reserva específica por su ID
    public function show($proj_id, $use_id, $id)
    {

        // Busca la reserva por su ID
        $reservation = Reservation::FindOne($id);

        // Si no existe la reserva, devuelve un mensaje de error

        if ($reservation == null) {
            return response()->json(['status' => False, 'message' => 'No existe la reserva.'], 400);
        } else {

            // Registra un evento de búsqueda específica en la tabla de reservas
            Controller::NewRegisterTrigger("Se realizó una busqueda en la tabla reservations.", 4, $proj_id, $use_id);

            // Devuelve un JSON con el estado y los datos de la reserva encontrada
            return response()->json(['status' => True, 'data' => $reservation], 200);
        }
    }

    // Método para actualizar una reserva por su ID
    public function update(Request $request, $proj_id, $use_id, $id)
    {

        // Reglas de validación para los datos de la reserva
        $rules = [
            'res_date' => ['required', 'regex:/^(\d{4})(\/|-)(0[1-9]|1[0-2])\2([0-2][0-9]|3[0-1])$/'],
            'res_start' => ['required', 'regex:/^([0-1][0-9]|2[0-3])(:)([0-5][0-9])$/'],
            'res_end' => ['required', 'regex:/^([0-1][0-9]|2[0-3])(:)([0-5][0-9])$/'],
            'spa_id' => 'required|integer',
            'use_id' => 'required|integer'

        ];

        // Mensajes personalizados para las reglas de validación
        $messages = [
            'res_date.required' => 'La fecha de la reserva es requerida.',
            'res_date.regex' => 'El formato de la fecha de la reserva no es valido.',
            'res_start.required' => 'La hora inicial de la reserva es requerida.',
            'res_start.regex' => 'El formato de la hora inicial de la reserva no es valido.',
            'res_end.required' => 'La hora final de la reserva es requerida.',
            'res_end.regex' => 'El formato de la hora final de la reserva no es valido.',
            'spa_id.required' => 'El espacio a reservar es requerido.',
            'use_id.required' => 'El usuario que realiza la reserva es requerido.'
        ];

        // Realiza la validación de los datos de entrada
        $validator = Validator::make($request->input(), $rules, $messages);

        // Si la validación falla, devuelve un mensaje de error
        if ($validator->fails()) {
            return response()->json([
                'status' => False,
                'message' => $validator->errors()->all()
            ], 400);
        } else {

            // Llama al método Amend del modelo Reservation para actualizar la reserva
            return Reservation::Amend($request, $proj_id, $use_id, $id);
        }
    }

    // Método para cambiar el estado (activar o desactivar) de una reserva por su ID
    public function destroy($proj_id, $use_id, $id)
    {
        // Busca la reserva por su ID
        $desactivate = Reservation::find($id);

        // Cambia el estado de la reserva (activa o inactiva)
        ($desactivate->res_status == 1) ? $desactivate->res_status = 0 : $desactivate->res_status = 1;
        $desactivate->save();

        // Mensaje de éxito según el estado cambiado
        $message = ($desactivate->res_status == 1) ? 'Activado' : 'Desactivado';

        // Registra un evento de cambio de estado en la tabla de reservas
        Controller::NewRegisterTrigger("Se cambio el estado de una reserva en la tabla reservations ", 2, $proj_id, $use_id);

        // Devuelve un JSON con el mensaje de éxito y los datos de la reserva actualizada
        return response()->json([
            'message' => '' . $message . ' exitosamente.',
            'data' => $desactivate
        ], 200);
    }

    // Método para filtrar las reservas según un campo específico y su valor
    public function reserFilters($proj_id, $use_id, $column, $data)
    {

        // Llama al método ReserFilters del modelo Reservation para filtrar las reservas
        $reservation = Reservation::ReserFilters($column, $data);

        // Si no hay reservas que coincidan con el filtro, devuelve un mensaje de error
        if ($reservation == null) {
            return response()->json([
                'status' => False,
                'message' => 'No se han hecho reservaciones'
            ], 400);
        } else {

            // Registra un evento de búsqueda en la tabla de reservas
            Controller::NewRegisterTrigger("Se realizó una busqueda en la tabla reservations ", 4, $proj_id, $use_id);

            // Devuelve un JSON con el estado y los datos de las reservas filtradas
            return response()->json([
                'status' => True,
                'data' => $reservation
            ], 200);
        }
    }

    // Método para obtener las reservas activas de un usuario específico
    public function activeReservUser($proj_id, $use_id, Request $request)
    {

        // Llama al método ActiveReservUser del modelo Reservation para obtener las reservas activas del usuario
        $reservation = Reservation::ActiveReservUser($use_id, $request);

        // Si no hay reservas activas para el usuario, devuelve un mensaje de error
        if ($reservation == null) {
            return response()->json([
                'status' => False,
                'message' => 'No se han hecho reservaciones'
            ], 400);
        } else {
            // Control de acciones
            Controller::NewRegisterTrigger("Se realizó una busqueda en la tabla reservations ", 4, $proj_id, $use_id);

            // Retorna los datos en un JSON
            return response()->json([
                'status' => True,
                'data' => $reservation
            ], 200);
        }
    }
    public function calendar($proj_id, $use_id)
    {

        // Llama al método ActiveReservUser del modelo Reservation para obtener las reservas activas del usuario
        $reservation = Reservation::Calendar();
        if ($reservation == null) {

        // Si no hay reservas activas, devuelve un mensaje de error
            return response()->json([
                'status' => False,
                'message' => 'No se han hecho reservaciones.'
            ], 400);
        } else {
            // Control de acciones
            Controller::NewRegisterTrigger("Se realizó una busqueda en la tabla reservations ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $reservation
            ], 200);
        }
    }

    // Función que trae las reservaciones activas de los usuarios
    public function users(Request $request)

    {
        if ($request->acc_administrator == 1) {
            $users = Reservation::users();
            if ($users != null) {
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

    // Busca las reservas existentes en la base de datos entre dos fechas
    public function betweenDates($proj_id, $use_id, $startDate, $endDate)
    {
        // en el modelo Reservation se ejecutará la función betweenDates y se le pasaran la fecha de inicio y de fin
        $reservations = Reservation::betweenDates($startDate, $endDate);
        if ($reservations == null) {
            return response()->json([
                'status' => False,
                'message' => 'No se encontraron reservaciones'
            ], 400);
        } else {
            Controller::NewRegisterTrigger("Se realizó una busqueda en la tabla reservations ", 4, $proj_id, $use_id);
            return response()->json([
                'status' => True,
                'data' => $reservations
            ], 200);
        }
    }

    public function uploadFile($proj_id, $use_id, Request $request)
    {
        $count = 0;
        $responses = [];

        // Verificar si hay archivo en la solicitud
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            // Guardar el archivo
            $file->storeAs('csv', $file->getClientOriginalName());
            // Leer el archivo CSV
            $csvData = array_map('str_getcsv', file($file->path()));

            // Procesar cada fila del CSV
            foreach ($csvData as $index => $row) {
                // Saltar la cabecera
                if ($index == 0) {
                    continue;
                }

                if (!is_array($row) || count($row) < 6) {
                    $responses[] = [
                        "error" => "Fila inválida: ".$index.". Faltan columnas o datos no válidos."
                    ];
                    continue;
                }

                // Extraer y validar datos del CSV
                $use_mail = trim($row[0]);
                $res_date = trim($row[1]);
                $res_start = trim($row[2]);
                $res_end = trim($row[3]);
                $res_status = strtolower(trim($row[4])) === 'activo' ? 1 : 0;
                $spa_name = trim($row[5]);

                // Verificar si faltan datos
                if (!$use_mail || !$res_date || !$res_start || !$res_end || !$spa_name) {
                    $responses[] = [
                        "error" => "Datos faltantes en la fila: ".$index
                    ];
                    continue;
                }

                // Buscar el usuario por correo
                $user = DB::table('users')->where('use_mail', $use_mail)->first();
                if (!$user) {
                    $responses[] = [
                        "error" => "Correo no encontrado: ".$use_mail
                    ];
                    continue;
                }

                // Buscar el espacio por nombre
                $space = DB::table('spaces')->where('spa_name', $spa_name)->first();
                if (!$space) {
                    $responses[] = [
                        "error" => "Espacio no encontrado: ".$spa_name
                    ];
                    continue;
                }

                // Preparar datos para la reserva
                $request->merge([
                    'res_date' => $res_date,
                    'res_start' => $res_start,
                    'res_end' => $res_end,
                    'res_status' => $res_status,
                    'spa_id' => $space->spa_id,
                    'use_id' => $user->use_id
                ]);


                if ($request->has('file')) {
                    $request->offsetUnset('file');
                }


                try {
                    $assistance = ReservationController::store($proj_id, $use_id, $request);
                    $data = json_decode($assistance->getContent(), true);

                    if ($data["status"] === false) {
                        $responses[] = [
                            "error" => $data["message"].'. Correo: '.$use_mail
                        ];
                    } else {
                        $count++;
                    }
                } catch (\Exception $e) {
                    $responses[] = [
                        "error" => "Error al procesar la fila: ".$index.". Detalles: ".$e->getMessage()
                    ];
                }
            }

            // Resumen del procesamiento
            $responses[] = [
                "status" => true,
                "message" => "Archivo procesado con éxito. Total registros creados: ".$count
            ];

            return response()->json([
                'status' => true,
                'message' => $responses
            ], 200);
        } else {
            return response()->json(['error' => 'No se encontró un archivo CSV en la solicitud.'], 400);
        }
    }


}
