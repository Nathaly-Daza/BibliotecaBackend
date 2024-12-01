<?php

namespace App\Http\Controllers;

use App\Models\Space;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class SpaceController extends Controller
{

    public function index($proj_id, $use_id)
    {
            $spaces = Space::all();
            if($spaces == null){
                return response()->json([
                'status' => False,
                'message' => 'There is no spaces availables.'
                ],400);
            }else{
                Controller::NewRegisterTrigger("Se realizó una busqueda de datos en la tabla spaces ",4,$proj_id,$use_id);

                return response()->json([
                    'status'=>True,
                    'data'=>$spaces],200);
            }
    }


    public function store($proj_id, $use_id, Request $request )
    {


            if ($request->acc_administrator == 1) {
            // Se establecen los parametros para ingresar datos.
            $rules =[
                'spa_name' => ['required','unique:spaces', 'regex:/^[A-ZÁÉÍÓÚÜÀÈÌÒÙÑ0-9\s]+$/']
            ];
            // El sistema valida que estos datos sean correctos
            $validator = Validator::make($request->input(), $rules);
            if ($validator->fails()) {
                return response()->json([
                    'status' => False,
                    'message' => $validator->errors()->all()
                ],400);
            }else{
                // Si los datos son correctos se procede a guardar los datos en la base de datos.
                $space = new Space($request->input());
                $space->spa_name = $request->spa_name;
                $space->spa_status = 1;
                $space ->save();
                // Se guarda la novedad en la base de datos.
                Controller::NewRegisterTrigger("Se realizó una inserción de un dato en la tabla spaces ",3,$proj_id,$use_id);
                return response()->json([
                    'status' => True,
                    'message' => 'space '.$space->spa_name.' created successfully',
                    'data' => $space
                ],200);
            }
        }else{
            return response()->json([
               'status' => False,
               'message' => 'Access denied. This action can only be performed by active administrators.'
                ],403);
        }
    }



    public function show($proj_id, $use_id, $id)
    {

        // Se busca el id que se pasa por URL en la tabla de la base de datos
        $space = Space::find($id);
        if($space == null){
            return response()->json([
                'status' => False,
                'message' => 'This space does not exist.'
            ],400);
        }else{
            // Se guarda la novedad en la base de datos.
            Controller::NewRegisterTrigger("Se realizó una busqueda de un dato específico en la tabla spaces.",4,$proj_id,$use_id);
            return response()->json([
            'status' => True,
            'data' => $space
            ],200);
        }

    }


    public function update($proj_id, $use_id, Request $request, $id )
    {
        if ($request->acc_administrator == 1) {
            // Se establecen los parametros para ingresar datos.
            $rules =[
                'spa_name' => ['required', 'unique:spaces', 'regex:/^[A-ZÁÉÍÓÚÜÀÈÌÒÙÑ0-9\s]+$/'],
            ];
            // El sistema valida que estos datos sean correctos
            $validator = Validator::make($request->input(), $rules);
            if ($validator->fails()) {
                return response()->json([
                    'status' => False,
                    'message' => $validator->errors()->all()
                ],400);
            }else{
                // Se busca el dato en la base de datos.
                $space = Space::find($id);
                $space->spa_name = $request->spa_name;
                $space->save();
                // Se guarda la novedad en la base de datos.
                Controller::NewRegisterTrigger("Se realizó una actualización en la información del dato ".$request->spa_name." de la tabla spaces ",1,$proj_id,$use_id);
                return response()->json([
                    'status' => True,
                    'message' => 'space '.$space->spa_name.' modified successfully',
                    'data'=> $space
                ],200);
            }

        }else{
            return response()->json([
              'status' => False,
              'message' => 'Access denied. This action can only be performed by active administrators.'
                ],403);
        }

    }


    // Función para cambiar el estado de un espacio por ID
    public function destroy( $proj_id, $use_id, $id, Request $request)
    {
        if($request->acc_administrator == 1){
            $desactivate = Space::find($id);
            ($desactivate->spa_status == 1)?$desactivate->spa_status=0:$desactivate->spa_status=1;
            $desactivate->save();
            Controller::NewRegisterTrigger("Se cambio el estado de un dato en la tabla spaces ",2,$proj_id,$use_id);
            return response()->json([
                'message' => 'Status of '.$desactivate->spa_name.' changed successfully.',
                'data' => $desactivate
            ],200);
        }else{
            return response()->json([
                'status' => False,
                'message' => 'Access denied. This action can only be performed by active administrators.'
            ]);
        }
    }

    public function uploadFile(Request $request)
    {
        //debe buscar en persons por per_document = documento del archivo. per_id agregar al request y depues llamar al store dentro del foreach y enviar el request
        $count = 0;
        $responses[] = [];
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $file->storeAs('csv', $file->getClientOriginalName());

            $csvData = array_map('str_getcsv', file($file->path()));

            foreach ($csvData as $index => $row) {
                if ($index == 0 && !is_numeric($row[0])) {
                    continue;
                }

                $document = trim($row[0]);

                // Verificar si el documento es un número válido
                if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $row[0])) {
                    return response()->json([
                        'status' => false,
                        'message' => "El archivo tiene datos invalidos."
                     ], 200);
                }

                $person = db::table('persons')->where('per_document', $row)->first();
                if($person == null){
                    $responses[] = [
                        "Datos invalidos: ".$row[0]
                    ];
                    continue;

                }


                // $assistance = new Assistance();
                // $assistance->ass_date = date('Y-m-d');
                // $assistance->ass_status = 1;
                // $assistance->stu_id = intval($row[0]);
                // $assistance->bie_act_id = $request->bie_act_id;
                // $assistance->ass_reg_status = 1;
                // $assistance->save();

                $request->merge(['use_id' => $person->use_id]);
                $assistance = SpaceController::store($request);
                $data = json_decode($assistance->getContent(), true);
                $status = $data;
                if($status["status"] == false){
                    $responses[] = [
                     $data["message"].'. Id: '.$person->per_document
                    ];
                }else{
                    $count = $count + 1;
                };

            }
            $responses[0] = [
                "status" => true,
                "message" => "The file has been uploaded successfully with ".$count." new registers"
            ];
            return response()->json([
               'status' => true,
               'message' => $responses
            ], 200);




        }else{
            return response()->json(['error' => 'No CSV file found in request'], 400);
        }

    }
}
