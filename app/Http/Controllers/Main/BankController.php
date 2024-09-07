<?php

namespace App\Http\Controllers\Main;

use App\Auth;
use App\Http\Controllers\Controller;

class BankController extends Controller
{
    public function getBank()
    {
        $bank = Auth::getBank();
        $bank = json_encode($bank);
        print_r($bank);
        exit;
    }
    public function getBankv2()
    {
        $bank = Auth::getBankv2();
        $bank = json_encode($bank);
        print_r($bank);
        exit;
    }
}
