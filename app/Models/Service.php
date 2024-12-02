<?php

namespace App\Models;


use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class Service extends Model
{
    use HasFactory;

    protected $primaryKey = 'ser_id';

    protected $fillable = [
        'ser_date',
        'ser_name',
        'ser_start',
        'ser_end',
        'ser_status',
        'ser_quotas',
        'ser_typ_id',
        'use_id',

    ];

    public $timestamps = false;

    public static function Select()
    {
        $services = DB::table('services AS ser')
            ->join('service_types AS st', 'st.ser_typ_id', '=', 'ser.ser_typ_id')
            ->join('users AS use', 'use.use_id', '=', 'ser.use_id')
            ->join('persons AS per', 'use.use_id', '=', 'per.use_id')

            ->select('ser.ser_id', 'ser.ser_name', 'ser.ser_date', 'ser.ser_start', 'ser.ser_end', 'ser.ser_status', 'ser_quotas', 'st.ser_typ_id', 'st.ser_typ_name','ser.use_id','use.use_mail', 'per.per_name', 'per_lastname')
            ->orderBy('ser.ser_id', 'DESC')->get();

        foreach ($services as $serviceKey) {

            $serviceKey->{'No. inscripciones'} = DB::table('biblioteca_inscriptions')
                ->join('services', 'services.ser_id', '=', 'biblioteca_inscriptions.ser_id')
                ->where('ser_name', $serviceKey->ser_name)
                ->where('bio_ins_status', 1)
                ->where('ser_date', $serviceKey->ser_date)
                ->where('ser_start', $serviceKey->ser_start)
                ->where('ser_end', $serviceKey->ser_end)
                ->count();
        }

        return $services;
    }

    public static function Store($proj_id, $use_id, $request)
    {

        $minHour = Carbon::create($request->ser_start);
        $minHour->add(30, "minute");
        $maxHour = Carbon::create($request->ser_start);
        $maxHour->add(2, "hour");
        $maxHourFormat = $maxHour->format("H:i");
        $minHourFormat = $minHour->format('H:i');
        // Fecha actual
        $date = date('Y-m-d');
        $actualHour = Carbon::now('America/Bogota')->format('H:i');
        // Trae todos los datos de usuarios y salas según el id que trae el request

        //$user = User::find($request->use_id);

        $user = DB::table('users')
        ->join('access', 'users.use_id', '=', 'access.use_id')
        ->join('persons', 'users.use_id', '=', 'persons.use_id')
        ->where('access.proj_id', 1)
        ->where('users.use_id', $request->use_id) // Agregar condición adicional
        ->select('users.use_id', 'users.use_status', 'users.use_mail', 'persons.per_name', 'persons.per_lastname')
        ->groupBy('users.use_id', 'users.use_mail', 'users.use_status', 'persons.per_name', 'persons.per_lastname')
        ->first();



        if ($user == null) {
            return response()->json([
                'status' => False,
                'message' => "El usario no es parte de el perosnal de biblioteca."
            ], 400);
        } elseif ($user->use_status == 0) {
            return response()->json([
                'status' => False,
                'message' => "El usuario no está disponible."
            ], 400);
        }
        // Convertimos los valores de hora que nos pasa el usuario a datos tipo Carbon
        $newSerStart = carbon::parse($request->ser_start);
        $newSerEnd = carbon::parse($request->ser_end);
        $serType = ServiceTypes::find($request->ser_typ_id);

        // Se comprueba que solo puedan hacerse reservas del mismo día o días posteriores y la zona horaria de la reserva.
        if ($request->ser_date >= $date && $request->ser_start >= "07:00" && $request->ser_end <= "19:00" && $serType->ser_typ_status != 0) {

            // Se comprueba que el profesional este habilitado
            if ($user->use_status != 0) {
                // Se comprueba que la reserva sea minimo de treinta minutos y máximo de dos horas.
                if ($request->ser_end >= $minHourFormat && $request->ser_start < $request->ser_end) {

                    $servicesSinceDate = DB::select("SELECT COUNT(services.ser_id) AS total_ser
                                                FROM services
                                                WHERE services.ser_date >= '$date'  AND services.ser_status = 1");
                    $servicesSinceDateCount = $servicesSinceDate[0]->total_ser;


                    $servicesUsers = DB::table('services AS ser')
                        ->join('users AS use', 'use.use_id', '=', 'ser.use_id')
                        ->join('persons AS per', 'use.use_id', '=', 'per.use_id')

                        ->select('ser.ser_id', 'ser.ser_date', 'ser.ser_start', 'ser.ser_end', 'ser.ser_status', 'per.per_name','per.per_lastname', 'use.use_id','use.use_mail')
                        ->where('ser.ser_date', '=', $request->ser_date)
                        ->get();

                    $validateDay = DB::table('services AS ser')
                        ->join('users AS use', 'use.use_id', '=', 'ser.use_id')
                        ->join('persons AS per', 'use.use_id', '=', 'per.use_id')

                        ->select('ser.ser_id', 'ser.ser_date', 'ser.ser_start', 'ser.ser_end', 'ser.ser_status', 'per.per_name','per.per_lastname', 'use.use_id','use.use_mail')
                        ->where('ser.ser_date', '=', $request->ser_date)
                        ->where('use.use_id', '=', $request->use_id)->get();
                    if ($servicesUsers->isEmpty()) {
                        if ($request->ser_date == $date && $request->ser_start <= $actualHour) {
                            return response()->json([
                                'status' => False,
                                'message' => 'La hora inicial de la reserva debe ser igual o mayor a ' . $actualHour . '.'
                            ], 400);
                        } else {
                            if ($validateDay->isEmpty()) {
                                // Los datos ingresados en el request se almacenan en un nuevo modelo Reservation
                                $services = new Service($request->input());
                                $services->ser_status = 1;
                                $services->save();
                                Controller::NewRegisterTrigger("Se realizó una inserción de datos en la tabla services ", 3, $proj_id, $use_id);
                                return response()->json([
                                    'status' => True,
                                    'message' => 'La reserva con el profesional ' . $user->per_name .' '.$user->per_lastname . ' se creo exitosamente el dia ' . $services->ser_date . '.',
                                ], 200);
                            } else {

                                // $ExistReservation = Reservation::TimeZone($validateDay, $request);
                                foreach ($validateDay as $validateDayKey) {
                                    // Pasamos los datos de la hora de reserva que llegan de la base de datos a tipo carbon
                                    $validatedSerStart = carbon::parse($validateDayKey->ser_start);
                                    $validatedSerEnd = carbon::parse($validateDayKey->ser_end);
                                    if ($newSerStart->lt($validatedSerEnd) && $newSerEnd->gt($validatedSerStart) && $validateDayKey->ser_status == 1) {
                                        // Hay superposición, la nueva reserva no es posible
                                        return response()->json([
                                            'status' => False,
                                            'message' => 'Este profesional está reservado.'
                                        ], 400);
                                    }
                                }
                                // Los datos ingresados en el request se almacenan en un nuevo modelo Reservation
                                $services = new Service($request->input());
                                $services->ser_status = 1;
                                $services->save();
                                Controller::NewRegisterTrigger("Se realizó una inserción de datos en la tabla services ", 3, $proj_id, $use_id);
                                return response()->json([
                                    'status' => True,
                                    'message' => 'La reserva con el profesional  ' . $user->per_name .' '.$user->per_lastname .' se creó exitosamente el dia ' . $services->ser_date . '.',
                                ], 200);
                            }
                        }
                        if (!empty($validateDay)) {
                            // return $validateDay;
                            foreach ($validateDay as $validateDayKey) {
                                // Pasamos los datos de la hora de reserva que llegan de la base de datos a tipo carbon
                                $validatedResStart = carbon::parse($validateDayKey->ser_start);
                                $validatedResEnd = carbon::parse($validateDayKey->ser_end);

                                if ($newSerStart->lt($validatedResEnd) && $newSerEnd->gt($validatedResStart) && $validateDayKey->ser_status == 1) {
                                    // Hay superposición, la nueva reserva no es posible
                                    return response()->json([
                                        'status' => False,
                                        'message' => 'Este profesional está reservado.'
                                    ], 400);
                                }
                            }
                            // Los datos ingresados en el request se almacenan en un nuevo modelo Reservation
                            $services = new Service($request->input());
                            $services->ser_status = 1;
                            $services->save();
                            Controller::NewRegisterTrigger("Se realizó una inserción de datos en la tabla services ", 3, $proj_id, $use_id);
                            return response()->json([
                                'status' => True,
                                'message' => 'La reserva con el profesional ' . $servicesUsersKey->per_name .  ' '  . $servicesUsersKey->per_lastname . ' se creo exitosamente el dia ' . $services->ser_date . '.',

                            ], 200);
                        } else {
                            // Los datos ingresados en el request se almacenan en un nuevo modelo Reservation
                            $services = new Service($request->input());
                            $services->ser_status = 1;
                            $services->save();
                            Controller::NewRegisterTrigger("Se realizó una inserción de datos en la tabla services ", 3, $proj_id, $use_id);
                            return response()->json([
                                'status' => True,
                                'message' => 'La reserva con el profesional  '. $user->per_name .' '.$user->per_lastname .' se creó exitosamente el dia ' . $services->ser_date . '.',

                            ], 200);
                        }
                    } else {
                        if ($request->ser_date == $date && $request->ser_start <= $actualHour) {
                            return response()->json([
                                'status' => False,
                                'message' => 'La hora inicial de la reserva debe ser igual o mayor a ' . $actualHour . '.'
                            ], 400);
                        }
                        if (!empty($validateDay)) {
                            foreach ($servicesUsers as $servicesUsersKey) {
                                // Pasamos los datos de la hora de reserva que llegan de la base de datos a tipo carbon
                                $validatedResStart = Carbon::parse($servicesUsersKey->ser_start);
                                $validatedResEnd = carbon::parse($servicesUsersKey->ser_end);
                                if ($newSerStart->lt($validatedResEnd) && $newSerEnd->gt($validatedResStart) && $servicesUsersKey->ser_status == 1 && $servicesUsersKey->use_id == $request->use_id) {
                                    // Hay superposición, la nueva reserva no es posible

                                    return response()->json([
                                        'status' => False,
                                        'message' => 'El profesional ' . $servicesUsersKey->per_name .  ' '  . $servicesUsersKey->per_lastname .' se encuentra ocupado en esta hora o fecha especifica.'
                                    ], 400);
                                }
                            }
                        }
                        // Los datos ingresados en el request se almacenan en un nuevo modelo Reservation
                        $services = new Service($request->input());
                        $services->ser_status = 1;
                        $services->save();
                        Controller::NewRegisterTrigger("Se realizó una inserción de datos en la tabla services ", 3, $proj_id, $use_id);
                        return response()->json([
                            'status' => True,
                            'message' => 'La reserva con el profesional ' . $servicesUsersKey->per_name .  ' '  . $servicesUsersKey->per_lastname . ' se creo exitosamente el dia ' . $services->ser_date . '.',
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'status' => False,
                        'message' => 'Hora invalida, la hora final debe ser mayor a la inicial y la reserva debe ser mínimo de 30 minutos.'
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => False,
                    'message' => 'El profesional ' . $user->per_name .' '.$user->per_lastname . ' no está disponible.'
                ], 400);
            }
        } else {
            $message = ($serType->ser_typ_status == 0)
                ? 'El tipo de servicio ' . $serType->ser_typ_name . ' está fuera de servicio.'
                : 'Hora invalida, el profesional se encuentra disponible entre las 7:00AM y las 7:00PM del ' . $date . ', o una fecha posterior.';
            return response()->json([
                'status' => False,
                'message' => $message
            ], 400);
        }
    }

    public static function FindOne($id)
    {
        $service = DB::table('services AS ser')
            ->join('users AS use', 'use.use_id', '=', 'ser.use_id')
            ->join('persons AS per', 'per.use_id', '=', 'use.use_id')
            ->select('ser.ser_id', 'ser.ser_name', 'ser.ser_date', 'ser.ser_start', 'ser.ser_end', 'ser.ser_status', 'ser.ser_quotas', 'per.per_name', 'per.per_lastname')
            ->where('ser.ser_id', '=', $id)
            ->first();
            if($service == null){
                return $service;
            }
        $service->{'No. inscripciones'} = DB::table('biblioteca_inscriptions')
            ->join('services', 'services.ser_id', '=', 'biblioteca_inscriptions.ser_id')
            ->where('ser_name', $service->ser_name)
            ->where('bio_ins_status', 1)
            ->where('ser_date', $service->ser_date)
            ->where('ser_start', $service->ser_start)
            ->where('ser_end', $service->ser_end)
            ->count();
        return $service;
    }

    public static function Amend($proj_id, $use_id, $request, $id)
    {

        $minHour = Carbon::create($request->ser_start);
        $minHour->add(30, "minute");
        $maxHour = Carbon::create($request->ser_start);
        $maxHour->add(2, "hour");
        $maxHourFormat = $maxHour->format("H:i");
        $minHourFormat = $minHour->format('H:i');
        // Fecha actual
        $date = date('Y-m-d');
        $actualHour = Carbon::now('America/Bogota')->format('H:i');
        // Trae todos los datos de usuarios y salas según el id que trae el request
        $services = Service::find($id);
        $services->{'No. inscripciones'} = DB::table('biblioteca_inscriptions')
            ->join('services', 'services.ser_id', '=', 'biblioteca_inscriptions.ser_id')
            ->where('ser_name', $services->ser_name)
            ->where('bio_ins_status', 1)
            ->where('ser_date', $services->ser_date)
            ->where('ser_start', $services->ser_start)
            ->where('ser_end', $services->ser_end)
            ->count();

        if ($services->{'No. inscripciones'} > $request->ser_quotas) {
            return response()->json([
                'status' => False,
                'message' => "El número de cupos es menor al numero de estudiantes actualmente registrados."
            ], 400);
        }
        $user = User::find($request->use_id);
        if ($user == null) {
            return response()->json([
                'status' => False,
                'message' => "El profesional no existe."
            ], 400);
        } elseif ($user->use_status == 0) {
            return response()->json([
                'status' => False,
                'message' => "El profesional no está disponible."
            ], 400);
        }
        $service = Service::find($id);
        if ($service == null) {
            return response()->json([
                'status' => False,
                'message' => "La reservación del servicio no existe."
            ], 400);
        } elseif ($service->ser_status == 0) {
            return response()->json([
                'status' => False,
                'message' => "La reservación del servicio no está disponible."
            ], 400);
        }
        // Convertimos los valores de hora que nos pasa el usuario a datos tipo Carbon

        $newSerStart = carbon::parse($request->ser_start);
        $newSerEnd = carbon::parse($request->ser_end);
        // Se comprueba que solo puedan hacerse reservas del mismo día o días posteriores y la zona horaria de la reserva.
        if ($request->ser_date >= $date && $request->ser_start >= "07:00" && $request->ser_end <= "19:00") {
            // Se comprueba que la sala este habilitada
            // Se comprueba que la reserva sea minimo de treinta minutos y máximo de dos horas.
            if ($request->ser_end >= $minHourFormat && $request->ser_end <= $maxHourFormat && $request->ser_start < $request->ser_end && $user->use_status != 0) {



                $totalservicesDay = DB::select("SELECT COUNT(services.ser_id) AS total_ser
                            FROM services
                            WHERE services.ser_date = '$request->ser_date' AND services.ser_status = 1");
                $totalservicesDayCount = $totalservicesDay[0]->total_ser;
                $servicesUsers = DB::table('services AS ser')
                    ->join('users AS use', 'use.use_id', '=', 'ser.use_id')
                    ->join('persons AS per', 'per.use_id', '=', 'use.use_id')

                    ->select('ser.ser_id', 'ser.ser_date', 'ser.ser_start', 'ser.ser_end', 'ser.ser_status', 'per.per_name','per.per_lastname', 'use.use_id')
                    ->where('ser.ser_date', '=', $request->ser_date)
                    ->get();

                $validateDay = DB::table('services AS ser')
                    ->join('users AS use', 'use.use_id', '=', 'ser.use_id')
                    ->join('persons AS per', 'per.use_id', '=', 'use.use_id')

                    ->select('ser.ser_id', 'ser.ser_date', 'ser.ser_start', 'ser.ser_end', 'ser.ser_status', 'per.per_name','per.per_lastname', 'use.use_id')
                    ->where('ser.ser_date', '=', $request->ser_date)
                    ->where('use.use_id', '=', $request->use_id)->get();


                if ($servicesUsers->isEmpty()) {
                    if ($request->ser_date == $date && $request->ser_start <= $actualHour) {
                        return response()->json([
                            'status' => False,
                            'message' => 'La hora inicial de la reserva debe ser igual o mayor a ' . $actualHour . '.'
                        ], 400);
                    }
                    if ($validateDay->isEmpty()) {
                        $services = Service::find($id);
                        $services->ser_name = $request->ser_name;
                        $services->ser_date = $request->ser_date;
                        $services->ser_start = $request->ser_start;
                        $services->ser_end = $request->ser_end;
                        $services->ser_quotas = $request->ser_quotas;
                        $services->ser_typ_id = $request->ser_typ_id;
                        $services->use_id = $request->use_id;
                        // Se guarda la actualización
                        $services->save();
                        // Reporte de novedad
                        Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla services ", 1, $proj_id, $use_id);
                        return response()->json([
                            'status' => True,
                            'message' => 'La reserva con el profesional  ' . $user->per_name .' '.$user->per_lastname . ' se actualizó exitosamente el dia ' . $services->ser_date . '.'
                        ], 200);
                    } else {
                        foreach ($validateDay as $validateDayKey) {
                            // Pasamos los datos de la hora de reserva que llegan de la base de datos a tipo carbon
                            $validatedSerStart = carbon::parse($validateDayKey->ser_start);
                            $validatedSerEnd = carbon::parse($validateDayKey->ser_end);
                            if ($validateDayKey->ser_id == $id) {
                                $services = Service::find($id);
                                $services->ser_date = $request->ser_date;
                                $services->ser_name = $request->ser_name;
                                $services->ser_start = $request->ser_start;
                                $services->ser_end = $request->ser_end;
                                $services->ser_quotas = $request->ser_quotas;
                                $services->ser_typ_id = $request->ser_typ_id;
                                $services->use_id = $request->use_id;
                                // Se guarda la actualización
                                $services->save();
                                // Reporte de novedad
                                Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla services ", 1, $proj_id, $use_id);

                                return response()->json([
                                    'status' => True,
                                    'message' => 'La reserva con el profesional '. $user->per_name .' '.$user->per_lastname . ' se actualizó exitosamente el dia ' . $services->ser_date . '.'
                                ], 200);
                            } elseif ($newSerStart->lt($validatedSerEnd) && $newSerEnd->gt($validatedSerStart) && $validateDayKey->ser_status == 1) {
                                // Hay superposición, la nueva reserva no es posible
                                return response()->json([
                                    'status' => False,
                                    'message' => 'Ya existe una reservación en este momento.'
                                ], 400);
                            }
                        }

                        $services = Service::find($id);
                        $services->ser_date = $request->ser_date;
                        $services->ser_name = $request->ser_name;
                        $services->ser_start = $request->ser_start;
                        $services->ser_end = $request->ser_end;
                        $services->ser_quotas = $request->ser_quotas;
                        $services->use_id = $request->use_id;
                        // Se guarda la actualización
                        $services->save();
                        // Reporte de novedad
                        Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla services ", 1, $proj_id, $use_id);

                        return response()->json([
                            'status' => True,
                            'message' => 'La reserva con el profesional ' . $user->per_name .' '.$user->per_lastname . ' se actualizó exitosamente el dia ' . $services->ser_date . '.'
                        ], 200);
                    }
                } else {
                    if ($request->ser_date == $date && $request->ser_start <= $actualHour) {
                        return response()->json([
                            'status' => False,
                            'message' => 'La hora inicial de la reserva debe ser igual o mayor a ' . $actualHour . '.'
                        ], 400);
                    }
                    if (!$validateDay->isEmpty()) {

                        foreach ($validateDay as $validateDayKey) {
                            $validatedSerStart = carbon::parse($validateDayKey->ser_start);
                            $validatedSerEnd = carbon::parse($validateDayKey->ser_end);

                            if ($newSerStart->lt($validatedSerEnd) && $newSerEnd->gt($validatedSerStart) && $validateDayKey->use_id == $request->use_id && $validateDayKey->ser_id != $id) {
                                // Hay superposición, la nueva reserva no es posible
                                return response()->json([
                                    'status' => False,
                                    'message' => 'Este profesional está reservado.'
                                ], 400);
                            }
                        }

                        foreach ($servicesUsers as $servicesUsersKey) {
                            $validatedResStart = carbon::parse($servicesUsersKey->ser_start);
                            $validatedResEnd = carbon::parse($servicesUsersKey->ser_end);
                            if ($newSerStart->lt($validatedResEnd) && $newSerEnd->gt($validatedResStart) && $request->use_id == $servicesUsersKey->use_id && $servicesUsersKey->ser_status == 1 && $servicesUsersKey->ser_id != $id) {
                                return response()->json([
                                    'status' => False,
                                    'message' => 'Ya existe una reservación en este momento.'
                                ], 400);
                            }

                            if ($servicesUsersKey->ser_id == $id) {
                                $services = Service::find($id);
                                $services->ser_date = $request->ser_date;
                                $services->ser_name = $request->ser_name;
                                $services->ser_start = $request->ser_start;
                                $services->ser_end = $request->ser_end;
                                $services->ser_quotas = $request->ser_quotas;
                                $services->ser_typ_id = $request->ser_typ_id;
                                $services->use_id = $request->use_id;
                                // Se guarda la novedad
                                $services->save();
                                // Reporte de novedad
                                Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla services ", 1, $proj_id, $use_id);

                                return response()->json([

                                    'status' => True,
                                    'message' => 'La reserva con el profesional ' . $user->per_name .' '.$user->per_lastname . ' se actualizó exitosamente el dia' . $services->ser_date . '.'
                                ], 200);
                            }
                        }

                        // return response()->json([
                        //     'status' => False,
                        //     'message' => 'Reserva invalida.'
                        // ], 400);
                        $services = Service::find($id);
                        $services->ser_date = $request->ser_date;
                        $services->ser_name = $request->ser_name;
                        $services->ser_start = $request->ser_start;
                        $services->ser_end = $request->ser_end;
                        $services->ser_quotas = $request->ser_quotas;
                        $services->ser_typ_id = $request->ser_typ_id;
                        $services->use_id = $request->use_id;
                        // Se guarda la novedad
                        $services->save();
                        // Reporte de novedad
                        Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla services ", 1, $proj_id, $use_id);

                        return response()->json([

                            'status' => True,
                            'message' => 'La reserva con el profesional ' . $user->per_name .' '.$user->per_lastname . ' se actualizó exitosamente el dia' . $services->ser_date . '.'
                        ], 200);
                    } else {
                        foreach ($validateDay as $validateDayKey) {
                            // Pasamos los datos de la hora de reserva que llegan de la base de datos a tipo carbon
                            $validatedSerStart = carbon::parse($validateDayKey->ser_start);
                            $validatedSerEnd = carbon::parse($validateDayKey->ser_end);
                            if ($newSerStart->lt($validatedSerEnd) && $newSerEnd->gt($validatedSerStart) && $validateDayKey->ser_status == 1) {
                                // Hay superposición, la nueva reserva no es posible
                                return response()->json([
                                    'status' => False,
                                    'message' => 'Ya existe una reserva en este momento.'
                                ], 400);
                            }
                        }
                        $services = Service::find($id);
                        $services->ser_date = $request->ser_date;
                        $services->ser_name = $request->ser_name;
                        $services->ser_start = $request->ser_start;
                        $services->ser_end = $request->ser_end;
                        $services->ser_quotas = $request->ser_quotas;
                        $services->ser_typ_id = $request->ser_typ_id;
                        $services->use_id = $request->use_id;
                        // Se guarda la novedad
                        $services->save();
                        // Reporte de novedad
                        Controller::NewRegisterTrigger("Se realizó una actualización de datos en la tabla services ", 1, $proj_id, $use_id);
                        return response()->json([
                            'status' => True,
                            'message' => 'La reserva con el profesional' . $user->per_name .' '.$user->per_lastname . ' se actualizó exitosamente el dia' . $services->ser_date . '.'
                        ], 200);
                    }
                }
            } else {
                return response()->json([
                    'status' => False,
                    'message' => 'Hora invalida, la hora final debe ser mayor a la inicial y la reserva debe ser mínimo de 30 minutos.'
                ], 400);
            }
        } else {
            $message = ($user->prof_status == 0)
                ? 'El profesional ' . $user->user_mail. ' no está disponible.'
                : 'Hora invalida, el profesional debe ser reservado entre las 7:00AM y las 7:00PM del ' . $date . ', o una fecha posterior.';
            return response()->json([
                'status' => False,
                'message' => $message
            ], 400);
        }
    }

    public static function ReserFilters($column, $data)
    {
        $reservation = DB::table('services')->select(
            'services.ser_id AS No. Servicio',
            'services.ser_name AS Nombre del servicio',
            'services.ser_date AS Fecha',
            'services.ser_start AS Hora inicio',
            'services.ser_end AS Hora fin',
            'services.ser_quotas AS Cupos',
            'service_types.ser_typ_name AS Tipo Servicio',
            'persons.per_name AS Profesional',

            'services.ser_status AS Estado'
        )->join('service_types', 'services.ser_typ_id', '=', 'service_types.ser_typ_id')
            ->join('users', 'users.use_id', '=', 'services.use_id')
            ->join('persons', 'persons.use_id', '=', 'users.per_id')
            ->where("services." . $column, 'like', '%' . $data . '%')->OrderBy("services." . $column, 'DESC')->get();
        return $reservation;
    }

    public static function ActiveServiceUser()
    {
        $date = date('Y-m-d');
        $reservation = DB::table('services')->select(
            'services.ser_id AS No. Servicio',
            'services.ser_name AS Nombre del servicio',
            'services.ser_date AS Fecha',
            'services.ser_start AS Hora inicio',
            'services.ser_end AS Hora fin',
            'services.ser_quotas AS Cupos',
            'service_types.ser_typ_name AS Tipo Servicio',
            'persons.per_name AS Profesional',

            'services.ser_status AS Estado'
        )->join('service_types', 'services.ser_typ_id', '=', 'service_types.ser_typ_id')
        ->join('users', 'users.use_id', '=', 'services.use_id')
        ->join('persons', 'persons.use_id', '=', 'users.per_id')
            ->where("ser_date", ">=", $date)->where("ser_status", "=", 1)->get();


        return $reservation;
    }
    public static function Calendar()
    {
        $date = date('Y-m-d');
        $reservation = DB::select("SELECT services.ser_id AS 'No. Servicio', services.ser_name AS 'Nombre del servicio',services.ser_date AS 'Fecha',
        services.ser_start AS 'Hora inicio', services.ser_end AS 'Hora fin', services.ser_quotas AS 'Cupos Totales',
        service_types.ser_typ_name AS 'Tipo Servicio', persons.per_name AS 'Profesional',
        services.ser_status AS 'Estado' FROM services INNER JOIN service_types ON services.ser_typ_id = service_types.ser_typ_id
        INNER JOIN users ON services.use_id = users.use_id
        INNER JOIN persons ON persons.use_id = users.use_id
        WHERE services.ser_date >= '$date' AND services.ser_status = 1");


        foreach ($reservation as $serviceKey) {

            $serviceKey->{'No. inscripciones'} = DB::table('biblioteca_inscriptions')
                ->join('services', 'services.ser_id', '=', 'biblioteca_inscriptions.ser_id')
                ->where('ser_name', $serviceKey->{'Nombre del servicio'})
                ->where('ser_status', 1)
                ->where('bio_ins_status', 1)
                ->where('ser_date', $serviceKey->{'Fecha'})
                ->where('ser_start', $serviceKey->{'Hora inicio'})
                ->where('ser_end', $serviceKey->{'Hora fin'})
                ->count();
        }
        return $reservation;
    }


    public static function betweenDates($startDate, $endDate)
    {
        return DB::select("SELECT services.ser_id AS 'No. Servicio', services.ser_name AS 'Nombre del servicio', services.ser_date AS 'Fecha',
        services.ser_start AS 'Hora inicio', services.ser_end AS 'Hora fin', services.ser_quotas AS Cupos,
        service_types.ser_typ_name AS 'Tipo Servicio', persons.per_name AS 'Profesional',
         services.ser_status AS 'Estado' FROM services INNER JOIN service_types ON services.ser_typ_id = service_types.ser_typ_id
        INNER JOIN users ON services.use_id = users.use_id
        INNER JOIN persons ON persons.use_id = users.use_id
        WHERE services.ser_date BETWEEN '$startDate' AND '$endDate'
        ORDER BY services.ser_date DESC");
    }

    public static function substractQuote($request)
    {
        $valQuotes = DB::table('services AS ser')
            ->select('ser.ser_id', 'ser.ser_quotas')
            ->where('ser.ser_id', $request->ser_id)
            ->first();

        $quotesCount = DB::table('biblioteca_inscriptions')
            ->where('ser_id', $request->ser_id)
            ->where('bio_ins_status', 1)
            ->count();
        // return $quotesCount;
        // return $valQuotes->ser_quotas;
        if ($valQuotes->ser_quotas > $quotesCount) {
            return true;
        } else {
            return false;
        }
    }

    public static function incriptionsPerService($id)
    {
        // $service = DB::table('services AS ser')
        //     ->join('profesionals AS pro', 'pro.use_id', '=', 'ser.use_id')
        //     ->select('ser.ser_id', 'ser.ser_name', 'ser.ser_date', 'ser.ser_start', 'ser.ser_end', 'ser.ser_status', 'ser.ser_quotas', 'pro.prof_name')
        //     ->where('ser.ser_id', '=', $id)
        //     ->first();
        // if ($service == null){
        //     return $service;
        // }else{
        $users = DB::table('biblioteca_inscriptions as bi')
            ->join('services as se', 'bi.ser_id', '=', 'se.ser_id')
            ->join('users as u', 'bi.use_id', '=', 'u.use_id')
            ->join('persons as pe', 'pe.use_id', '=', 'u.use_id')
            ->select('bi.bio_ins_id', 'bi.bio_ins_date', 'u.use_mail', 'bi.bio_ins_status', 'se.ser_date', 'bi.ser_id', 'pe.per_name', 'pe.per_lastname', 'pe.per_document')
            ->where('bi.ser_id', $id)
            ->where('bi.bio_ins_status', 1)
            ->orderBy('bi.bio_ins_date', 'asc')
            ->get();
        return $users;
        // }
    }

    public static function users()
    {
        $users = DB::select(
            "SELECT us.use_id,  MAX(us.use_mail) AS use_mail ,MAX(acc.acc_id) AS acc_id    FROM users us
            LEFT JOIN access acc ON us.use_id = acc.use_id GROUP BY us.use_id"
        );


        $Allusers = DB::select(
            "SELECT acc.use_id, acc.proj_id, us.use_mail , acc.acc_id  FROM users us
            LEFT JOIN access acc ON us.use_id = acc.use_id "
        );
        foreach($users as $user){
            $user->acc_admin = 0;
            foreach($Allusers as $fullUser){
                if($fullUser->use_id == $user->use_id && $fullUser->proj_id == 1){
                    $user->acc_admin = 1;
                }
            }

        }
        return $users;
    }
}
