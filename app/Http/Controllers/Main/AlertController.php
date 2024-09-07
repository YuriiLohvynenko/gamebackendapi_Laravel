<?php

namespace App\Http\Controllers\Main;

use App\Auth;
use App\Http\Controllers\Controller;

class AlertController extends Controller
{
    public function Alert()
    {
        $Alert = Auth::Alert();
        $Alert = json_encode($Alert);
        print_r($Alert);
        exit;
    }
}
