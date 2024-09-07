<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Auth;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    public function getCheckcredit(Request $request)
    {
        if (!isset($request->ip) && !isset($request->token)){
            $res = array(
                'state'   => 'error',
                'message' => 'กรอกข้อมูลให้ครบถ้วน'
            );
            echo json_encode($res);
            exit;
        }

        $data = array(
            'ip' => $request->ip,
            'token' => $request->token
        );


        $check = Auth::getCredit($data);
        print_r($check);
        exit;
    }
}
