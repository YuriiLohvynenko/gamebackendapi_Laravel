<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Auth;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function getProfile(Request $request)
    {
        if (!isset($request->ip) && !isset($request->token)){
            $res = array(
                'state'   => 'error',
                'message' => 'Complete the information' //กรอกข้อมูลให้ครบถ้วน
            );
            echo json_encode($res);
            exit;
        }

        $data = array(
            'ip' => $request->ip,
            'token' => $request->token
        );


        $check = Auth::getProfile($data);
        print_r($check);
        exit;
    }
}
