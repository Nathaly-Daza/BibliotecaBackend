<?php

namespace App\Models;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class BibliotecaInscription extends Model
{
    use HasFactory;
    protected $table = 'biblioteca_inscriptions';
    protected $primaryKey = 'bio_ins_id';

    protected $fillable = [
        'bio_ins_date',
        'bio_ins_status',
        'use_id',
        'ser_id'
    ];

    public $timestamps = false;

    public static function select(){
        return DB::table('biblioteca_inscriptions as bi')
            ->join('services as se', 'bi.ser_id', '=','se.ser_id')
            ->join('users as u', 'bi.use_id', '=','u.use_id')
            ->select('bi.bio_ins_id', 'bi.bio_ins_date', 'se.ser_name', 'bi.bio_ins_status', 'bi.use_id', 'bi.ser_id','se.ser_date','se.ser_start','se.ser_end','se.ser_status','se.ser_quotas', 'u.use_mail')
            ->orderBy('bi.bio_ins_date', 'asc')
            ->get();
    }

    public static function make(Request $request, $proj_id, $use_id)
    {
        $date = date('Y-m-d');
        $services = DB::table('biblioteca_inscriptions as bi')
            ->join('services as se', 'se.ser_id', '=', 'bi.ser_id')
            ->select('se.ser_id', 'se.ser_date', 'se.ser_start', 'se.ser_end', 'se.ser_status')
            ->where('bi.use_id', '=', $request->use_id)
            ->where('se.ser_status', '=', 1)
            ->get();
        $service =  Service::find($request->ser_id);
        $newSerStart = Carbon::parse($service->ser_start);
        $newSerEnd = Carbon::parse($service->ser_end);
        // Se establecen los parametros para ingresar datos.
        $rules = [
            'use_id' => ['required'],
            'ser_id' => ['required']

        ];
        // El sistema valida que estos datos sean correctos
        // if ($date <= $request->bio_ins_date) {


        foreach ($services as $myService) {
            // Pasamos los datos de la hora de reserva que llegan de la base de datos a tipo carbon

            if ($myService->ser_date == $service->ser_date) {
                $validatedSerStart = carbon::parse($myService->ser_start);
                $validatedSerEnd = carbon::parse($myService->ser_end);
                if ($newSerStart->lt($validatedSerEnd) && $newSerEnd->gt($validatedSerStart)) {
                    // Hay superposición, la nueva reserva no es posible
                    return response()->json([
                        'status' => False,
                        'message' => 'Ya se encuentra inscrito en un servicio en ese momento.'
                    ], 400);
                }
            }
        }

        $validator = Validator::make($request->input(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => False,
                'message' => $validator->errors()->all()
            ], 400);
        } else {
            // Si los datos son correctos se procede a guardar los datos en la base de datos.

            $student =  DB::table('users as u')->where('u.use_id', '=', $request->use_id)->first();
            if ($service == null || $student == null) {
                $message = ($service == null) ? 'El servicio no existe.' : ' El usuario no existe.';
                return response()->json([
                    'status' => False,
                    'message' => $message
                ], 400);
            }

            if ($service->ser_status == 1) {
                $biblioteca = new BibliotecaInscription($request->input());
                $biblioteca->bio_ins_date = $date;
                $biblioteca->bio_ins_status = 1;
                $quotas = Service::substractQuote($request);
                if ($quotas == true) {
                    $biblioteca->save();
                    // Se guarda la novedad en la base de datos.
                    Controller::NewRegisterTrigger("Se realizó una busqueda de datos en la tabla biblioteca_Inscriptions ", 4, $proj_id, $use_id);
                    return response()->json([
                        'status' => True,
                        'message' => 'Inscrito correctamente.',
                        'data' => $biblioteca
                    ], 200);
                } else {
                    return response()->json([
                        'status' => False,
                        'message' => 'No ha sido posible crear la inscripción.'
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => False,
                    'message' => 'Este servicio no está disponible.'
                ], 400);
            }
        }
        // } else {
        //     return response()->json([
        //         'status' => False,
        //         'message' => 'Fecha invalida.'
        //     ], 400);
        // }
    }


    public static function findOne($id){
        return DB::table('biblioteca_inscriptions as bi')
            ->join('services as se', 'bi.ser_id', '=','se.ser_id')
            ->join('users as u', 'bi.use_id', '=','u.use_id')
            ->select('bi.bio_ins_id', 'bi.bio_ins_date', 'se.ser_name', 'bi.bio_ins_status', 'bi.use_id', 'bi.ser_id','se.ser_date','se.ser_start','se.ser_end','se.ser_status','se.ser_quotas', 'u.use_mail')
            ->where('bi.bio_ins_id', $id)
            ->orderBy('bi.bio_ins_date', 'asc')
            ->first();
    }

    public static function Amend(Request $request, $proj_id, $use_id, $id)
    {
        $date = date('Y-m-d');
        $services = DB::table('biblioteca_inscriptions as bi')
            ->join('services as se', 'se.ser_id', '=', 'bi.ser_id')
            ->select('bi.bio_ins_id','se.ser_id', 'se.ser_date', 'se.ser_start', 'se.ser_end', 'se.ser_status')
            ->where('bi.use_id', '=', $request->use_id)
            ->where('se.ser_status', '=', 1)
            ->get();
        $service =  Service::find($request->ser_id);
        $newSerStart = Carbon::parse($service->ser_start);
        $newSerEnd = Carbon::parse($service->ser_end);
        // Se establecen los parametros para ingresar datos.
        $rules = [
            'use_id' => ['required'],
            'ser_id' => ['required']

        ];
        // El sistema valida que estos datos sean correctos
        // if ($date <= $request->bio_ins_date) {
        foreach ($services as $myService) {
            // Pasamos los datos de la hora de reserva que llegan de la base de datos a tipo carbon

            if ($myService->ser_date == $service->ser_date && $myService->bio_ins_id != $id) {
                $validatedSerStart = carbon::parse($myService->ser_start);
                $validatedSerEnd = carbon::parse($myService->ser_end);
                if ($newSerStart->lt($validatedSerEnd) && $newSerEnd->gt($validatedSerStart)) {
                    // Hay superposición, la nueva reserva no es posible
                    return response()->json([
                        'status' => False,
                        'message' => 'Ya se encuentra inscrito en un servicio en ese momento.'
                    ], 400);
                }
            }
        }
        $validator = Validator::make($request->input(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => False,
                'message' => $validator->errors()->all()
            ], 400);
        } else {

            $biblioteca =  BibliotecaInscription::find($id);
            $service =  Service::find($request->ser_id);
            $student =  DB::table('users AS u')->where('u.use_id', '=', $request->use_id)->first();
            if ($biblioteca == null || $service == null || $student == null) {
                $message = ($biblioteca == null) ? 'La inscripción no existe.' : ($service == null ? 'El servicio no existe.' : ' El usuario no existe.');
                return response()->json([
                    'status' => False,
                    'message' => $message
                ], 400);
            } else {
                // if ($biblioteca->ser_id == $request->ser_id) {
                //     // El usuario solo podrá cambiar el tipo de servicio
                //     $biblioteca->bio_ins_date = $date;
                //     $biblioteca->ser_id = $request->ser_id;
                //     $biblioteca->save();
                //     Controller::NewRegisterTrigger("Se realizó una actualizacion de datos en la tabla biblioteca_Inscriptions ", 4, $proj_id, $use_id);
                //     return response()->json([
                //         'status' => True,
                //         'message' => 'La inscripción se actualizó correctamente.',
                //         'data' => $biblioteca
                //     ], 200);
                // } else {
                if ($service->ser_status == 1) {
                    $quotas = Service::substractQuote($request);
                    // return $quotas;
                    if ($quotas == false) {
                        return response()->json([
                            'status' => False,
                            'message' => 'No hay cupos disponibles en este servicio.'
                        ]);
                    } else {

                        // Se actualizará la fecha en el día que se genere el cambio.
                        $biblioteca->bio_ins_date = $date;
                        $biblioteca->use_id = $request->use_id;
                        $biblioteca->ser_id = $request->ser_id;
                        $biblioteca->save();
                        // Se guarda la novedad en la base de datos.
                        Controller::NewRegisterTrigger("Se realizó una actualizacion de datos en la tabla biblioteca_Inscriptions ", 4, $proj_id, $use_id);
                        return response()->json([
                            'status' => True,
                            'message' => 'La inscripción se actualizó correctamente.',
                            'data' => $biblioteca
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'status' => False,
                        'message' => 'Este servicio no está disponible.'
                    ]);
                }
                // }7
            }
        }
        // } else {
        //     return response()->json([
        //         'status' => False,
        //         'message' => 'Fecha invalida.'
        //     ], 400);
        // }
    }

    public static function studentActive($id){
        return DB::table('biblioteca_inscriptions as bi')
            ->join('services as se', 'bi.ser_id', '=','se.ser_id')
            ->select('bi.bio_ins_id','se.ser_name', 'bi.bio_ins_date', 'bi.bio_ins_status', 'bi.use_id', 'bi.ser_id','se.ser_date','se.ser_start','se.ser_end','se.ser_status','se.ser_quotas')
            ->where('bi.use_id', $id)
            ->where('bi.bio_ins_status', 1)
            ->orderBy('bi.bio_ins_date', 'asc')
            ->get();
    }


}
