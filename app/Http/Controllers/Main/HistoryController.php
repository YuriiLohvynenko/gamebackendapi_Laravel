<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Auth;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function getHistory(Request $request)
    {
        if (!isset($request->id) && !isset($request->data)){
            $res = array(
                'state'   => 'error',
                'message' => 'กรอกข้อมูลให้ครบถ้วน'
            );
            echo json_encode($res);
            exit;
        }

        $data = array(
            'id' => $request->id
        );


        // $check = Auth::getHistory($data);

        if ($request->data == 'deposit') {
            $deposit['deposit'] = json_decode(Auth::getDeposits($data), true);
        } else if ($request->data == 'transfer') {
            $deposit['transfer'] = json_decode(Auth::getTransfers($data), true);
        } else if ($request->data == 'withdraw') {
            $deposit['withdraw'] = json_decode(Auth::getWithdraws($data), true);
        } else if ($request->data == 'all') {
            $deposit['deposit'] = json_decode(Auth::getDeposits($data), true);
            $deposit['transfer'] = json_decode(Auth::getTransfers($data), true);
            $deposit['withdraw'] = json_decode(Auth::getWithdraws($data), true);
        }

        echo json_encode(array("data" => $deposit, "state" => "success"));
        exit;
    }

    public function getHistoryv2(Request $request)
    {
        if (!isset($request->id) && !isset($request->data)){
            $res = array(
                'state'   => 'error',
                'message' => 'กรอกข้อมูลให้ครบถ้วน'
            );
            echo json_encode($res);
            exit;
        }

        $data = array(
            'id' => $request->id
        );

        if ($request->data == 'deposit') {
            $sass = json_decode(Auth::getDepositsv2($data), true);
            // $deposit = json_decode(Auth::getDepositsv2($data), true);
            $deposit['_total'] = $sass['total'];
            $deposit['_data'] = $sass['data'];
        } else if ($request->data == 'transfer') {
            $sass = json_decode(Auth::getTransfersv2($data), true);
            // $deposit = json_decode(Auth::getTransfersv2($data), true);
            $deposit['_total'] = $sass['total'];
            $deposit['_data'] = $sass['data'];
        } else if ($request->data == 'withdraw') {
            $sass = json_decode(Auth::getWithdrawsv2($data), true);
            // $deposit = json_decode(Auth::getWithdrawsv2($data), true);
            $deposit['_total'] = $sass['total'];
            $deposit['_data'] = $sass['data'];
        }
        return $this->responseRequestSuccess($deposit);
        // echo json_encode(array("data" => $deposit, "state" => "success"));
        // exit;
    }

    protected function responseRequestSuccess($ret)
    {
        return response()->json(['status' => 'success', 'data' => $ret], 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }
}
