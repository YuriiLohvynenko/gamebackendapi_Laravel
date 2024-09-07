<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Auth;

class RegisterController extends Controller
{

  public function viewRegister()
  {
    return view('auth.register');
  }

  public function register(Request $request)
  {
    

    if (($request->username == null) or
      ($request->password == null) or
      ($request->passwordconf == null) or
      ($request->firstname == null) or
      ($request->lastname == null) or
      ($request->regisip == null) or
      ($request->bankid == null)  or
      ($request->bankaccount == null) or
      ($request->line == null)  or
      ($request->referer == null) or
      ($request->sex == null) or
      ($request->age == null)
    ) {
      echo json_encode(array(
        'state'   => 'error',
        'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
      ));
      exit;
    }

    if ($request->password != $request->passwordconf) {
      echo json_encode(array(
        'state'   => 'error',
        'message' => 'รหัสผ่านไม่ตรงกัน กรุณาลองใหม่อีกครั้ง'
      ));
      exit;
    }

    if (strlen($request->username) != 10 || !is_numeric($request->username)) {
      echo json_encode(array(
        'state'   => 'error',
        'message' => 'Wrong phone number'
      ));
      exit;
    }

    if (!preg_match('/^(?=.*\d)(?=.*[A-Za-z])[0-9A-Za-z]{8,24}$/', $request->password)) {
      echo json_encode(array(
        'state'   => 'error',
        'message' => 'รหัสผ่านควรมี 8 ถึง 24 ตัวอักษร และต้องมีตัวพิมน์ใหญ่อย่างน้อย 1 ตัวอักษร (0-9, a-z, A-Z) อักษรพิเศษไม่สามารถใช้งานได้'
      ));
      exit;
    }

    if (strlen($request->firstname) < 1 || strlen($request->lastname) < 1) {
      echo json_encode(array(
        'state'   => 'error',
        'message' => 'ชื่อและนามสกุล ผิดพลาด'
      ));
      exit;
    }

    $freecode = Auth::freecode(4);
    $freecode1 = Auth::freecode1(4);
    $freecode2 = Auth::freecode2(4);
    $random = Auth::randomuser(8);
    // echo $freecode . '-' . $freecode1. '-' . $freecode2;
    // return;
    $data = array(
      'SLOT_USER' => config('app.AG_KEY') . $random,
      'SL_USERNAME' => $request->username,
      'SL_PASSWORD' => $request->password,
      'SL_FIRSTNAME' => $request->firstname,
      'SL_LASTNAME' => $request->lastname,
      'SL_REGISIP' => $request->regisip,
      'SL_REGISDATE' => date('Y-m-d H:i:s'),
      'SL_BANK_ID' => $request->bankid,
      'SL_BANKID' => $request->bankaccount,
      'SL_LINEID' => $request->line,
      'SL_FREECODE' => $freecode . '-' . $freecode1. '-' . $freecode2,
      'SL_REFERER' => $request->referer,
      'SL_SEX' => $request->sex,
      'SL_AGE' => $request->age
    );

    $response = Auth::registerUser($data);

    // print_r($response);
    // exit;

    if ($response == 1) {
      echo json_encode(array(
        'state'   => 'success',
        'message' => 'Register Successfully!'
      ));
      exit;
    } else if ($response == 0) {
      echo json_encode(array(
        'state'   => 'error',
        'message' => 'Mobile number ' . $request->username . ' Has been used'
      ));
      exit;
    } else if ($response == 2) {
      echo json_encode(array(
        'state'   => 'error',
        'message' => 'This name ' . $request->firstname . ' ' . $request->lastname . ' Has been used'
      ));
      exit;
    } else if ($response == 3) {
      echo json_encode(array(
        'state'   => 'error',
        'message' => 'Account number ' . $request->bankaccount . ' Has been used'
      ));
      exit;
    } else if ($response == 4) {
      echo json_encode(array(
        'state'   => 'error',
        'message' => 'Cannot connect to the system Please contact admin'
      ));
      exit;
    } else if ($response == 5) {
      echo json_encode(array(
        'state'   => 'error',
        'message' => "I can't find the desired bank. Please use the bank that we provide."
      ));
      exit;
    }
  }
}
