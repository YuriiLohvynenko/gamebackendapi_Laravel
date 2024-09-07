<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Auth;
use Illuminate\Http\Request;

class TruewalletController extends Controller
{
    public function Truewallet(Request $request)
    {

        if (!isset($request->customerid) && !isset($request->gift)){
            $res = array(
                'state'   => 'error',
                'message' => 'กรอกข้อมูลให้ครบถ้วน'
            );
            echo json_encode($res);
            exit;
        }

        $data = array(
            'customerid' => $request->customerid,
            'gift' => $request->gift,
        );

        $transf = Auth::Truewallet($data);

        print_r($transf);
        exit;
    }
}
