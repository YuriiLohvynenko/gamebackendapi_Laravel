<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Auth;

class LoginController extends Controller
{
  public function viewLogin()
  {
    return view('auth.login');
  }

  public function login(Request $request)
  {
    if (!isset($request->username) && !isset($request->password) && !isset($request->ip)) {
      $res = array(
        'state'   => 'error',
        'message' => 'กรอกข้อมูลให้ครบถ้วน'
      );
      echo json_encode($res);
      exit;
    }
    $data = array(
      'username'    => $request->username,
      'password' => $request->password,
      'ip' => $request->ip
    );

    $response = Auth::login($data);

    print_r($response);
    exit;
  }

  public function checkUser(Request $request)
  {
    if (!isset($request->username)) {
      $res = array(
        'state'   => 'error',
        'message' => 'กรอกข้อมูลให้ครบถ้วน'
      );
      echo json_encode($res);
      exit;
    }
    $data = array(
      'username'    => $request->username
    );

    $response = Auth::checkUser($data);

    print_r($response);
    exit;
  }

  public function chgPassword(Request $request)
  {

    if (!isset($request->ip) && !isset($request->token) && !isset($request->username) && !isset($request->old_pass) && !isset($request->new_pass)) {
      $res = array(
        'state'   => 'error',
        'message' => 'กรอกข้อมูลให้ครบถ้วน'
      );
      echo json_encode($res);
      exit;
    }

    $data = array(
      'ip' => $request->ip,
      'token' => $request->token,
      'username' => $request->username,
      'old_pass' => $request->old_pass,
      'new_pass' => $request->new_pass
    );

    $response = Auth::chgPassword($data);

    if ($response == "1") {
      $res = array(
        'state'   => 'success',
        'message' => 'เปลี่ยนรหัสผ่านสำเร็จ รหัสผ่านใหม่คือ : '.$request->new_pass
      );
    } else if ($response == "2") {
      $res = array(
        'state'   => 'error',
        'message' => 'The old password is not correct!' // รหัสผ่านเก่าไม่ถูกต้อง!
      );
    } else if ($response == "3") {
      $res = array(
        'state'   => 'error',
        'message' => "ไม่พบยูสเซอร์"
      );
    } else if ($response == "4") {
      $res = array(
        'state'   => 'error',
        'message' => "Can't connect to the server Please contact admin" // ม่สามารถเชื่อมจ่อกับเซริฟ์เวอร์ได้ กรุณาติดต่อแอดมิน
      );
    } else if ($response == "5") {
      $res = array(
        'state'   => 'error',
        'message' => "รหัสผ่านควรมี 8 ถึง 24 ตัวอักษร และต้องมีตัวพิมน์ใหญ่อย่างน้อย 1 ตัวอักษร (0-9, a-z, A-Z) อักษรพิเศษไม่สามารถใช้งานได้"
      );
    } else if ($response == "6") {
      $res = array(
        'state'   => 'error',
        'message' => "Sorry, you are using this password. Please change the password to a different one!" // ขออภัยคุณใช้รหัสผ่านนี้อยู่ กรุณาเปลี่ยนรหัสผ่านใหม่ให้ต่างจากเดิม!
      );
    }

    $res = json_encode($res);
    echo $res;
  }
}
