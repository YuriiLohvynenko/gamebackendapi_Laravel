<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Auth;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function getCoupon(Request $request)
    {
        if (!isset($request->ip) && !isset($request->token) && !isset($request->code) && !isset($request->captcha)) {
            $res = array(
                'state'   => 'error',
                'message' => 'กรอกข้อมูลให้ครบถ้วน'
            );
            echo json_encode($res);
            exit;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); //timeout in seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $form_field = array();
        $form_field['secret'] = "6LejGyYaAAAAAP8bCl2lwvQbLMhvbAyotsc4gOtj";
        $form_field['response'] = $request->captcha;
        $form_field['remoteip'] = $request->ip;
        $post_string = '';

        foreach ($form_field as $key => $value) {
            $post_string .= $key . '=' . urlencode($value) . '&';
        }

        $post_string = substr($post_string, 0, -1);

        curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);

        $response = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($response, true);

        // $arr = array('message' => $response, 'state' => 'error');
        // echo json_encode($arr);
        // exit;

        if ($json['success'] == true && $json['action'] == 'coupon') {

            $data = array(
                'ip' => $request->ip,
                'token' => $request->token,
                'code' => $request->code,
                'refId' => sha1($request->captcha)
            );

            $check = Auth::getCoupon($data);
            print_r($check);
            exit;
        } else {
            $arr = array('message' => 'กรุณาลองอีกครั้ง! เออเร่อแคปช่า', 'state' => 'error');
            echo json_encode($arr);
            exit;
        }
    }
}
