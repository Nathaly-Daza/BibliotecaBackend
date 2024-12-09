<?php

namespace App\Models;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Psy\CodeCleaner\ReturnTypePass;

class Reservation extends Model
{
    use HasFactory;

    protected $primaryKey = 'res_id';

    protected $fillable = [
        'res_date',
        'res_start',
        'res_end',
        'res_status',
        'spa_id',
        'use_id',
        'isRecurring',
        'recurrenceType',
        'recurrenceEndDate',

    ];

    public $timestamps = false;

    public static function Select()
    {
        $reservations = DB::table('reservations AS res')
            ->join('spaces AS sp', 'sp.spa_id', '=', 'res.spa_id')
            ->join('users AS u', 'u.use_id', '=', 'res.use_id')
            ->select('res.res_id', 'res.res_date', 'res.res_start', 'res.res_end', 'res.res_status', 'sp.spa_name', 'u.use_mail', 'u.use_id')
            ->orderBy('res.res_id', 'DESC')->get();
        return $reservations;
    }
    public static function Store($proj_id, $use_id, $request)
    {

        $minHour = Carbon::create($request->res_start);
        $minHour->add(30, "minute");
        $maxHour = Carbon::create($request->res_start);
        $maxHour->add(2, "hour");
        $maxHourFormat = $maxHour->format("H:i");
        $minHourFormat = $minHour->format('H:i');
        // Fecha actual
        $date = date('Y-m-d');
        $actualHour = Carbon::now('America/Bogota')->format('H:i');
        // Trae todos los datos de usuarios y salas según el id que trae el request
        $user = User::find($request->use_id);
        if ($user == null) {
            return response()->json([
                'status' => False,
                'message' => "El usuario no existe."
            ], 400);
        }
        $space = Space::find($request->spa_id);
        if ($space == null) {
            return response()->json([
                'status' => False,
                'message' => "El espacio no existe."
            ], 400);
        } elseif ($space->spa_status == 0) {
            return response()->json([
                'status' => False,
                'message' => "El espacio no está disponible."
            ], 400);
        }

        // Convertimos los valores de hora que nos pasa el usuario a datos tipo Carbon
        $newResStart = carbon::parse($request->res_start);
        $newResEnd = carbon::parse($request->res_end);
        // Se comprueba que solo puedan hacerse reservas del mismo día o días posteriores y la zona horaria de la reserva.
        // Se comprueba que la sala este habilitada
        if ($request->res_date >= $date && $request->res_start >= "07:00" && $request->res_end <= "19:00") {
            // Se comprueba que la reserva sea minimo de treinta minutos y máximo de dos horas.
            if ($request->res_end >= $minHourFormat && $request->res_end <= $maxHourFormat && $request->res_start < $request->res_end) {

                $reservationsSinceDate = DB::select("SELECT COUNT(reservations.res_id) AS total_res
                    FROM reservations
                    WHERE reservations.res_date >= '$date'  AND reservations.use_id = $request->use_id AND reservations.res_status = 1");

                $reservationsSinceDateCount = $reservationsSinceDate[0]->total_res;
                if ($reservationsSinceDateCount < 3 || $request->acc_administrator == 1) {
                    $reserUsers = DB::table('reservations AS res')
                        ->join('spaces AS sp', 'sp.spa_id', '=', 'res.spa_id')
                        ->join('users AS u', 'u.use_id', '=', 'res.use_id')
                        ->select('res.res_id', 'res.res_date', 'res.res_start', 'res.res_end', 'res.res_status', 'sp.spa_name', 'sp.spa_id', 'u.use_id')
                        ->where('res.res_date', '=', $request->res_date)
                        ->where('u.use_id', '=', $request->use_id)->get();

                    $validateDay = DB::table('reservations AS res')
                        ->join('spaces AS sp', 'sp.spa_id', '=', 'res.spa_id')
                        ->join('users AS u', 'u.use_id', '=', 'res.use_id')
                        ->select('res.res_id', 'res.res_date', 'res.res_start', 'res.res_end', 'res.res_status', 'sp.spa_name', 'sp.spa_id', 'u.use_id')
                        ->where('res.res_date', '=', $request->res_date)
                        ->get();

                    if ($reserUsers->isEmpty()) {

                        if ($request->res_date == $date && $request->res_start <= $actualHour) {
                            return response()->json([
                                'status' => False,
                                'message' => 'La hora inicial de la reserva debe ser igual o mayor a ' . $actualHour . '.'
                            ], 400);
                        } else {

                            if ($validateDay->isEmpty()) {


                                // Los datos ingresados en el request se almacenan en un nuevo modelo Reservation
                                $reservations = new Reservation($request->input());
                                $reservations->res_status = 1;
                                $reservations->save();
                                Controller::NewRegisterTrigger("Se realizó una inserción de datos en la tabla reservations ", 3, $proj_id, $use_id);
                                return response()->json([
                                    'status' => True,
                                    'message' => 'La reserva en el espacio ' . $space->spa_name . ' se creo exitosamente el dia ' . $reservations->res_date . ' por el usuario: ' . $user->use_mail . '.',
                                ], 200);
                            } else {

                                foreach ($validateDay as $validateDayKey) {
                                    // Pasamos los datos de la hora de reserva que llegan de la base de datos a tipo carbon
                                    $validatedResStart = carbon::parse($validateDayKey->res_start);
                                    $validatedResEnd = carbon::parse($validateDayKey->res_end);
                                    // return $validateDay;
                                    if ($newResStart->lt($validatedResEnd) && $newResEnd->gt($validatedResStart) && $validateDayKey->res_status == 1 && $validateDayKey->spa_id == $space->spa_id) {
                                        // Hay superposición, la nueva reserva no es posible
                                        return response()->json([
                                            'status' => False,
                                            'message' => 'Ya hay una reserva en este horario o espacio. '
                                        ], 400);
                                    }
                                }
                                foreach ($reserUsers as $userReser) {

                                    $validatedResStart = carbon::parse($userReser->res_start);
                                    $validatedResEnd = carbon::parse($userReser->res_end);
                                    if ($newResStart->lt($validatedResEnd) && $newResStart == $validatedResStart && $newResEnd == $validatedResEnd && $newResEnd->gt($validatedResStart) && $validateDayKey->res_status == 1 && $userReser->spa_id == $space->spa_id) {
                                        // Hay superposición, la nueva reserva no es posible
                                        return response()->json([
                                            'status' => False,
                                            'message' => 'Este Usuario ya tiene una reserva a esta hora.'
                                        ], 400);
                                    }
                                }


                                // Los datos ingresados en el request se almacenan en un nuevo modelo Reservation
                                $reservations = new Reservation($request->input());
                                $reservations->res_status = 1;
                                $reservations->save();
                                Controller::NewRegisterTrigger("Se realizó una inserción de datos en la tabla reservations ", 3, $proj_id, $use_id);
                                return response()->json([
                                    'status' => True,
                                    'message' => 'La reserva en el espacio  ' . $space->spa_name . ' se creó exitosamente el dia ' . $reservations->res_date . ' por el usuario: ' . $user->use_mail . '.',
                                ], 200);
                            }
                        }
                    } else {

                        if ($request->res_date == $date && $request->res_start <= $actualHour) {
                            return response()->json([
                                'status' => False,
                                'message' => 'La hora inicial de la reserva debe ser igual o mayor a ' . $actualHour . '.'
                            ], 400);
                        }
                        if ($validateDay->isNotEmpty()) {
                            foreach ($reserUsers as $reserUsersKey) {
                                $validatedResStart = carbon::parse($reserUsersKey->res_start);
                                $validatedResEnd = carbon::parse($reserUsersKey->res_end);
                                if (
                                    $newResStart->lt($validatedResEnd) && $newResEnd->gt($validatedResStart) && $request->use_id == $reserUsersKey->use_id && $reserUsersKey->res_status == 1
                                ) {
                                    return response()->json([
                                        'status' => False,
                                        'message' => 'Ya existe una reservación en este momento.'
                                    ], 400);
                                }
                            }
                        }

                        $reservations = new Reservation($request->input());
                        $reservations->res_status = 1;
                        $reservations->save();
                        // Se guarda la novedad
                        Controller::NewRegisterTrigger("Se realizó una inserción de datos en la tabla reservations ", 1, $proj_id, $use_id);

                        return response()->json([
                            'status' => True,
                            'message' => 'La reserva en el espacio ' . $space->spa_name . ' se realizó exitosamente el dia' . $reservations->res_date . ' por el usuario: ' . $user->use_mail . '.'
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'status' => False,
                        'message' => 'Solo puedes tener 3 reservaciones activas.'
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => False,
                    'message' => 'Hora invalida, la hora final debe ser mayor a la inicial y el rango de la reserva debe ser mínimo de 30 minutos y máximo 2 horas.'
                ], 400);
            }
        } else {
            $message = ($space->spa_status == 0)
                ? 'El espacio ' . $space->spa_name . ' no está disponible.'
                : 'Hora invalida, el espacio debe ser reservado entre las 7:00AM y las 7:00PM del ' . $date . ', o una fecha posterior.';
            return response()->json([
                'status' => False,
                'message' => $message
            ], 400);
        }
    }
    public static function FindOne($id)
    {
        $reservation = DB::table('reservations AS res')
            ->join('spaces AS sp', 'sp.spa_id', '=', 'res.spa_id')
            ->join('users AS u', 'u.use_id', '=', 'res.use_id')
            ->select('res.res_id', 'res.res_date', 'res.res_start', 'res.res_end', 'res.res_status', 'sp.spa_name', 'u.use_mail', 'u.use_id')
            ->where('reservations.res_id', '=', $id)->first();
        return $reservation;
    }
    public static function Amend(Request $request, $proj_id, $use_id, $id)
    {

        $minHour = Carbon::create($request->res_start);
        $minHour->add(30, "minute");
        $maxHour = Carbon::create($request->res_start);
        $maxHour->add(2, "hour");
        $maxHourFormat = $maxHour->format("H:i");
        $minHourFormat = $minHour->format('H:i');
        // Fecha actual
        $date = date('Y-m-d');
        $actualHour = Carbon::now('America/Bogota')->format('H:i');
        // Trae todos los datos de usuarios y salas según el id que trae el request
        $user = User::find($request->use_id);
        if ($user == null) {
            return response()->json([
                'status' => False,
                'message' => "El usuario no existe."
            ], 400);
        }
        $space = Space::find($request->spa_id);
        if ($space == null) {
            return response()->json([
                'status' => False,
                'message' => "El espacio no existe."
            ], 400);
        } elseif ($space->spa_status == 0) {
            return response()->json([
                'status' => False,
                'message' => "El espacio no está disponible."
            ], 400);
        }
        $reservation = Reservation::find($id);
        if ($reservation == null) {
            return response()->json([
                'status' => False,
                'message' => "La reservación no existe."
            ], 400);
        } elseif ($reservation->res_status == 0) {
            return response()->json([
                'status' => False,
                'message' => "La reservación no está disponible."
            ], 400);
        }
        // Convertimos los valores de hora que nos pasa el usuario a datos tipo Carbon
        $newResStart = carbon::parse($request->res_start);
        $newResEnd = carbon::parse($request->res_end);
        // Se comprueba que solo puedan hacerse reservas del mismo día o días posteriores y la zona horaria de la reserva.
        // Se comprueba que la sala este habilitada
        if ($request->res_date >= $date && $request->res_start >= "07:00" && $request->res_end <= "19:00" && $space->spa_status != 0) {
            // Se comprueba que la reserva sea minimo de treinta minutos y máximo de dos horas.
            if ($request->res_end >= $minHourFormat && $request->res_end <= $maxHourFormat && $request->res_start < $request->res_end) {

                $totalReservationsDay = DB::select("SELECT COUNT(res_id) AS total_res
                        FROM reservations
                        WHERE res_date = '$request->res_date' AND reservations.use_id = $request->use_id  AND reservations.res_status = 1");

                $totalReservationsDayCount = $totalReservationsDay[0]->total_res;
                if ($totalReservationsDayCount < 3 || $request->acc_administrator == 1) {

                    $reserUsers = DB::table('reservations AS res')
                        ->join('spaces AS sp', 'sp.spa_id', '=', 'res.spa_id')
                        ->join('users AS u', 'u.use_id', '=', 'res.use_id')
                        ->select('res.res_id', 'res.res_date', 'res.res_start', 'res.res_end', 'res.res_status', 'sp.spa_name', 'sp.spa_id', 'u.use_id')
                        ->where('res.res_date', '=', $request->res_date)
                        ->where('u.use_id', '=', $request->use_id)->get();
                    // Se comprueba que el sistema no tenga una reservación en ese día
                    $validateDay = DB::table('reservations AS res')
                        ->join('spaces AS sp', 'sp.spa_id', '=', 'res.spa_id')
                        ->join('users AS u', 'u.use_id', '=', 'res.use_id')
                        ->select('res.res_id', 'res.res_date', 'res.res_start', 'res.res_end', 'res.res_status', 'sp.spa_name', 'sp.spa_id', 'u.use_id')
                        ->where('res.res_date', '=', $request->res_date)
                        ->where('sp.spa_id', '=', $request->spa_id)
                        ->orderBy('res.res_date', 'DESC')->get();
                    if ($reserUsers->isEmpty()) {

                        if ($request->res_date == $date && $request->res_start <= $actualHour) {
                            return response()->json([
                                'status' => False,
                                'message' => 'La hora inicial de la reserva debe ser igual o mayor a ' . $actualHour . '.'
                            ], 400);
                        }
                        if ($validateDay->isEmpty()) {
                            $reservations = Reservation::find($id);
                            $reservations->res_date = $request->res_date;
                            $reservations->res_start = $request->res_start;
                            $reservations->res_end = $request->res_end;
                            $reservations->spa_id = $request->spa_id;
                            $reservations->use_id = $request->use_id;
                            // Se guarda la actualización
                            $reservations->save();
                            // Reporte de novedad
                            Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla reservations ", 1, $proj_id, $use_id);
                            return response()->json([
                                'status' => True,
                                'message' => 'La reserva en el espacio  ' . $space->spa_name . ' se actualizó exitosamente el dia ' . $reservations->res_date . ' por el usuario: ' . $user->use_mail . '.'
                            ], 200);
                        } else {
                            foreach ($validateDay as $validateDayKey) {
                                // Pasamos los datos de la hora de reserva que llegan de la base de datos a tipo carbon
                                $validatedResStart = carbon::parse($validateDayKey->res_start);
                                $validatedResEnd = carbon::parse($validateDayKey->res_end);
                                if ($validateDayKey->res_id == $id) {
                                    $reservations = Reservation::find($id);
                                    $reservations->res_date = $request->res_date;
                                    $reservations->res_start = $request->res_start;
                                    $reservations->res_end = $request->res_end;
                                    $reservations->spa_id = $request->spa_id;
                                    $reservations->use_id = $request->use_id;
                                    // Se guarda la actualización
                                    $reservations->save();
                                    // Reporte de novedad
                                    Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla reservations ", 1, $proj_id, $use_id);
                                    return response()->json([
                                        'status' => True,
                                        'message' => 'La reserva en el espacio ' . $space->spa_name . ' se actualizó exitosamente el dia ' . $reservations->res_date . ' por el usuario: ' . $user->use_mail . '.'
                                    ], 200);
                                }
                                if ($newResStart->lt($validatedResEnd) && $newResEnd->gt($validatedResStart) && $validateDayKey->res_status == 1) {
                                    // Hay superposición, la nueva reserva no es posible
                                    return response()->json([
                                        'status' => False,
                                        'message' => 'Ya existe una reservación en este momento.'
                                    ], 400);
                                }
                            }
                        }
                    } else {

                        if ($request->res_date == $date && $request->res_start <= $actualHour) {
                            return response()->json([
                                'status' => False,
                                'message' => 'La hora inicial de la reserva debe ser igual o mayor a ' . $actualHour . '.'
                            ], 400);
                        }
                        if ($validateDay->isEmpty()) {

                            foreach ($reserUsers as $reserUsersKey) {
                                $validatedResStart = carbon::parse($reserUsersKey->res_start);
                                $validatedResEnd = carbon::parse($reserUsersKey->res_end);
                                if ($reserUsersKey->res_id == $id) {

                                    $reservations = Reservation::find($id);
                                    $reservations->res_date = $request->res_date;
                                    $reservations->res_start = $request->res_start;
                                    $reservations->res_end = $request->res_end;
                                    $reservations->spa_id = $request->spa_id;
                                    $reservations->use_id = $request->use_id;
                                    // Se guarda la novedad
                                    $reservations->save();
                                    // Reporte de novedad
                                    Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla reservations ", 1, $proj_id, $use_id);
                                    return response()->json([
                                        'status' => True,
                                        'message' => 'La reserva en el espacio ' . $space->spa_name . ' se actualizó exitosamente el dia' . $reservations->res_date . ' por el usuario: ' . $user->use_mail . '.'
                                    ], 200);
                                } elseif ($newResStart->lt($validatedResEnd) && $newResEnd->gt($validatedResStart) && $request->spa_id == $reserUsersKey->spa_id && $reserUsersKey->res_status == 1) {
                                    return response()->json([
                                        'status' => False,
                                        'message' => 'Ya existe una reservación en este momento.'
                                    ], 400);
                                } elseif ($newResStart->lt($validatedResEnd) && $newResEnd->gt($validatedResStart) && $request->spa_id != $reserUsersKey->spa_id && $reserUsersKey->res_status == 1 && $request->acc_administrator == 0) {
                                    return response()->json([
                                        'status' => False,
                                        'message' => 'Ya tiene una reserva en este momento'
                                    ], 400);
                                }
                            }

                            // Si el foreach no para en ningún if al salir se actualizará la reserva.
                            $reservations = Reservation::find($id);
                            $reservations->res_date = $request->res_date;
                            $reservations->res_start = $request->res_start;
                            $reservations->res_end = $request->res_end;
                            $reservations->spa_id = $request->spa_id;
                            $reservations->use_id = $request->use_id;
                            // Se guarda la novedad
                            $reservations->save();
                            // Reporte de novedad
                            Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla reservations ", 1, $proj_id, $use_id);
                            return response()->json([
                                'status' => True,
                                'message' => 'La reserva en el espacio ' . $space->spa_name . ' se actualizó exitosamente el dia' . $reservations->res_date . ' por el usuario: ' . $user->use_mail . '.'
                            ], 200);
                        } else {
                            foreach ($validateDay as $validateDayKey) {
                                // Pasamos los datos de la hora de reserva que llegan de la base de datos a tipo carbon
                                $validatedResStart = carbon::parse($validateDayKey->res_start);
                                $validatedResEnd = carbon::parse($validateDayKey->res_end);
                                if ($newResStart->lt($validatedResEnd) && $newResEnd->gt($validatedResStart) && $validateDayKey->res_status == 1 && $validateDayKey->res_id != $id) {
                                    // Hay superposición, la nueva reserva no es posible
                                    return response()->json([
                                        'status' => False,
                                        'message' => 'Este espacio está reservado'
                                    ], 400);
                                }
                            }

                            foreach ($reserUsers as $reserUsersKey) {
                                $validatedResStart = carbon::parse($reserUsersKey->res_start);
                                $validatedResEnd = carbon::parse($reserUsersKey->res_end);
                                if ($newResStart->lt($validatedResEnd) && $newResEnd->gt($validatedResStart) && $request->spa_id != $reserUsersKey->spa_id && $reserUsersKey->res_status == 1 && $request->acc_administrator == 0) {
                                    return response()->json([
                                        'status' => False,
                                        'message' => 'Ya tiene una reserva en este momento'
                                    ], 400);
                                }
                            }
                            $reservations = Reservation::find($id);
                            $reservations->res_date = $request->res_date;
                            $reservations->res_start = $request->res_start;
                            $reservations->res_end = $request->res_end;
                            $reservations->spa_id = $request->spa_id;
                            $reservations->use_id = $request->use_id;
                            // Se guarda la novedad
                            $reservations->save();
                            // Reporte de novedad
                            Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla reservations ", 1, $proj_id, $use_id);
                            return response()->json([
                                'status' => True,
                                'message' => 'La reserva en el espacio ' . $space->spa_name . ' se actualizó exitosamente el dia' . $reservations->res_date . ' por el usuario: ' . $user->use_mail . '.'
                            ], 200);
                        }
                        return 'aaaa';
                    }
                } else {
                    return response()->json([
                        'status' => False,
                        'message' => 'Este usuario no puede hacer mas reservaciones.'
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => False,
                    'message' => 'Hora invalida, la hora final debe ser mayor a la inicial y el rango de la reserva debe ser mínimo de 30 minutos y máximo 2 horas.'
                ], 400);
            }
        } else {
            $message = ($space->spa_status == 0)
                ? 'El espacio ' . $space->spa_name . ' no está disponible.'
                : 'Hora invalida, el espacio debe ser reservado entre las 7:00AM y las 7:00PM del ' . $date . ', o una fecha posterior.';
            return response()->json([
                'status' => False,
                'message' => $message
            ], 400);
        }
        /*       $request->validate([
            'res_date' => 'required|date',
            'res_start' => 'required|date_format:H:i',
            'res_end' => 'required|date_format:H:i|after:res_start',
            'recurrence_type' => 'nullable|in:weekly,monthly',
            'recurrence_end_date' => 'nullable|date|after:res_date',
        ]);
        if (!empty($request->recurrence_type) && !empty($request->recurrence_end_date)) {
            $currentDate = Carbon::create($request->res_date);
            $endDate = Carbon::create($request->recurrence_end_date);

            while ($currentDate <= $endDate) {
                $request->merge(['res_date' => $currentDate->format('Y-m-d')]);

                // Validar cada reserva con la lógica existente
                $validationResponse = self::validateReservation($request, $proj_id, $use_id);
                if ($validationResponse !== true) {
                    return $validationResponse; // Si falla, retorna el error
                }

                // Crear reserva
                self::createReservation($request, $proj_id, $use_id);

                // Avanzar la fecha según el tipo de recurrencia
                if ($request->recurrence_type == "weekly") {
                    $currentDate->addWeek();
                } elseif ($request->recurrence_type == "monthly") {
                    $currentDate->addMonth();
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Reservas recurrentes creadas exitosamente.',
            ], 200);
        } */
    }
    public static function ReserFilters($column, $data, $proj_id)
    {
        $reservation = DB::table('reservations AS res')
            ->join('spaces AS sp', 'res.spa_id', '=', 'sp.spa_id')
            ->join('users AS us', 'res.use_id', '=', 'us.use_id')
            ->select(
                'res.res_id AS No. Reserva',
                'res.res_date AS Fecha',
                'res.res_start AS Hora inicio',
                'res.res_end AS Hora fin',
                'sp.spa_name AS Espacio',
                'us.use_mail AS Correo',
                'res.res_status AS Estado',
            )
            ->where("res." . $column, 'like', '%' . $data . '%')->where('sp.proj_id', $proj_id)->OrderBy("res." . $column, 'DESC')->get();
        return $reservation;
    }

    public static function ActiveReservUser($use_id, $proj_id, $request)
    {
        $date = date('Y-m-d');
        // Ternario
        $reservation = ($request->acc_administrator == 1)
            ? DB::table('reservations AS res')
            ->join('spaces AS sp', 'res.spa_id', '=', 'sp.spa_id')
            ->join('users AS us', 'res.use_id', '=', 'us.use_id')
            ->select(
                'res.res_id AS No. Reserva',
                'res.res_date AS Fecha',
                'res.res_start AS Hora inicio',
                'res.res_end AS Hora fin',
                'res.res_status AS Estado',
                'sp.spa_name AS Espacio',
                'us.use_mail AS Correo'
            )
            ->where("res_date", ">=", $date)
            ->where("res_status", "=", 1)
            ->where('sp.proj_id', $proj_id)
            ->OrderBy("res.use_id", 'DESC')->get()

            : DB::table('reservations AS res')
            ->join('spaces AS sp', 'res.spa_id', '=', 'sp.spa_id')
            ->join('users AS us', 'res.use_id', '=', 'us.use_id')
            ->select(
                'res.res_id AS No. Reserva',
                'res.res_date AS Fecha',
                'res.res_start AS Hora inicio',
                'res.res_end AS Hora fin',
                'res.res_status AS Estado',
                'sp.spa_name AS Espacio',
                'us.use_mail AS Correo'
            )
            ->where("res.use_id", '=', $use_id)
            ->where("res_date", ">=", $date)
            ->where("res_status", "=", 1)
            ->where('sp.proj_id', $proj_id)
            ->OrderBy("res.use_id", 'DESC')->get();
        return $reservation;
    }
    public static function Calendar($proj_id)
    {
        $date = date('Y-m-d');
        $reservation = DB::select("SELECT reservations.res_id AS 'No. Reserva', reservations.res_date AS 'Fecha',
        reservations.res_start AS 'Hora inicio', reservations.res_end AS 'Hora fin', spaces.spa_name AS 'Espacio',
        users.use_mail AS 'Correo', reservations.use_id AS 'Identificacion', reservations.res_status AS 'Estado' FROM reservations
        INNER JOIN spaces ON reservations.spa_id = spaces.spa_id
        INNER JOIN users ON reservations.use_id = users.use_id
        WHERE reservations.res_date >= '$date' AND reservations.res_status = 1 AND spaces.proj_id = $proj_id");
        return $reservation;
    }

    public static function users()
    {
        $users = DB::select(
            "SELECT us.use_id,  MAX(us.use_mail) AS use_mail ,MAX(acc.acc_id) AS acc_id    FROM users us
            LEFT JOIN access acc ON us.use_id = acc.use_id GROUP BY us.use_id"
        );


        $Allusers = DB::select(
            "SELECT acc.use_id, acc.proj_id, us.use_mail , acc.acc_id  FROM users us
            LEFT JOIN access acc ON us.use_id = acc.use_id"
        );
        foreach ($users as $user) {
            $user->acc_admin = 0;
            foreach ($Allusers as $fullUser) {
                if ($fullUser->use_id == $user->use_id && $fullUser->proj_id == 1) {
                    $user->acc_admin = 1;
                }
            }
        }
        return $users;
    }
    public static function betweenDates($proj_id, $startDate, $endDate)
    {

        return DB::table('reservations AS res')
            ->join('spaces AS sp', 'res.spa_id', '=', 'sp.spa_id')
            ->join('users AS us', 'res.use_id', '=', 'us.use_id')
            ->select(
                'res.res_id AS No. Reserva',
                'res.res_date AS Fecha',
                'res.res_start AS Hora inicio',
                'res.res_end AS Hora fin',
                'res.res_status AS Estado',
                'sp.spa_name AS Espacio',
                'us.use_mail AS Correo'
            )
            ->whereBetween('res.res_date', [$startDate, $endDate])
            ->where('sp.proj_id', $proj_id)
            ->OrderBy("res.res_date", 'DESC')->get();
    }
}
