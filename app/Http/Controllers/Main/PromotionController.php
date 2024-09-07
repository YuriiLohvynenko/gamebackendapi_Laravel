<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Auth;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function getPromotion(Request $request)
    {
        $response = Auth::getPromotion();
        print_r($response);
        exit;
    }
}
