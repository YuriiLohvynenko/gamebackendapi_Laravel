<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Transfer extends Model
{

    public static function transfer($data)
    {

        $io = DB::table('account_session')->where('SL_SESSION', $data['token'])->where('SL_LOGINIP', $data['ip'])->get()->first();

        if ($io) {

            $customerid = $io->CustomerID;
            $bonus = $data['bonus'];
            $DEPOSIT_TOTAL = 0;
            $WITHDRAW_TOTAL = 0;

            $bb = DB::table('account_users')->where('CustomerID', $customerid)->get()->first();

            if ($bb) {
                $SLOT_USERNAME = $bb->SLOT_USER;
                $SL_USERNAME = $bb->SL_USERNAME;
                $SL_FIRSTNAME = $bb->SL_FIRSTNAME;
                $SL_LASTNAME = $bb->SL_LASTNAME;
                $SL_BANKID = $bb->SL_BANKID;
                $SL_STATUS = $bb->SL_STATUS;
            } else {
                $arr = array('message' => 'ไม่พบยูสเซอร์', 'state' => 'error');
                return json_encode($arr);
            }

            $refId = DB::table('logs_transferlog')->where('REFID', $data['refId'])->get()->first();

            if($refId){
                $arr = array('message' => 'ไม่สามารถย้ายเงินได้ เลขรายการซ้ำกัน', 'state' => 'error');
                return json_encode($arr);
            }

            //เช็คยอดฝาก
            $a = DB::table('logs_depositlog')->distinct()->select('DEPOSIT_TXID')->where('CustomerID', $customerid)->groupBy('DEPOSIT_TXID')->get();

            foreach ($a as $res) {
                $b = DB::table('logs_depositlog')->where('DEPOSIT_TXID', $res->DEPOSIT_TXID)->where('CustomerID', $customerid)->get()->first();

                $DEPOSIT_TOTAL = $DEPOSIT_TOTAL + $b->DEPOSIT_AMOUNT;
            }

            //เช็คยอดโยกย้าย
            $c = DB::table('logs_transferlog')->distinct()->select('WITHDRAW_TXID')->where('CustomerID', $customerid)->where('WITHDRAW_STATUS', 2)->groupBy('WITHDRAW_TXID')->get();

            foreach ($c as $res) {
                $d = DB::table('logs_transferlog')->where('WITHDRAW_TXID', $res->WITHDRAW_TXID)->where('CustomerID', $customerid)->get()->first();

                $WITHDRAW_TOTAL = $WITHDRAW_TOTAL + $d->WITHDRAW_AMOUNT;
            }

            $e = DB::table('logs_balance')->where('CustomerID', $customerid)->get()->first();

            if ($e) {

                $OLD_BALANCE = $e->SL_BALANCE_OLD;

                $WALLET_BALANCE = ($OLD_BALANCE + $DEPOSIT_TOTAL) - $WITHDRAW_TOTAL;

                if ($WALLET_BALANCE < 0) {
                    $WALLET_BALANCE = 0;
                }

                DB::table('logs_balance')->where('CustomerID', $customerid)->update(['SL_DEPOSIT' => $DEPOSIT_TOTAL, 'SL_WITHDRAW' => $WITHDRAW_TOTAL, 'SL_BALANCE' => $WALLET_BALANCE]);
            } else {

                $oinsert = array(
                    'CustomerID' => $customerid,
                    'SL_DEPOSIT' => 0,
                    'SL_WITHDRAW' => 0,
                    'SL_BALANCE' => 0,
                );

                DB::table('logs_balance')->insert($oinsert);
            }

            if (!is_numeric($data['amount'])) {
                $arr = array('message' => 'กรุณากรอกจำนวนเงินให้ถูกต้อง', 'state' => 'error');
                return json_encode($arr);
            }

            $g = DB::table('credit_withdrawlog')->where('CustomerID', $customerid)->where('WITHDRAW_STATUS', 1)->get()->first();

            if ($g) {
                $arr = array('message' => 'ไม่สามารถย้ายเงินได้ คุณมีรายการถอนที่กำลังรอดำเนินการอยู่คะ', 'state' => 'error');
                return json_encode($arr);
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 400); //timeout in seconds
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $form_field = array();
            $form_field['username'] = $SLOT_USERNAME;
            $post_string = '';

            foreach ($form_field as $key => $value) {
                $post_string .= $key . '=' . urlencode($value) . '&';
            }

            $post_string = substr($post_string, 0, -1);

            curl_setopt($ch, CURLOPT_URL, 'https://connect.mafia88.club/?agent=' . config('app.AG_AGENT') . '&method=gc');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);

            $response = curl_exec($ch);
            curl_close($ch);
            $json = json_decode($response);

            if ($json->{'result'} == 'ok') {
                $credit_balance = str_replace(",", "", $json->{'balance'});

                if ($credit_balance < 5) {
                    DB::table('credit_turnover')->where('CustomerID', $customerid)->where('TURN_STATUS', 0)->update(['TURN_STATUS' => 1]);
                }

                if ($data['amount'] < 1) {
                    $arr = array('message' => 'ไม่สามารถโยกเงินได้คะ เนื่องจากคุณลูกค้ามีจำนวนเงินน้อยกว่า 1 บาท', 'state' => 'error');
                    return json_encode($arr);
                }

                if ($credit_balance >= 5) {
                    $arr = array('message' => 'ไม่สามารถโยกเงินได้คะ เนื่องจากคุณลูกค้ามีจำนวนเงินมากกว่า 5 บาท', 'state' => 'error');
                    return json_encode($arr);
                } else {

                    $h = DB::table('logs_balance')->where('CustomerID', $customerid)->get()->first();
                    if ($h) {
                        $wallet_before = $h->SL_BALANCE;

                        if ($wallet_before < $data['amount'] || $wallet_before == 0) {
                            $arr = array('message' => 'ไม่สามารถโยกเงินได้คะ เนื่องจากจำนวนเงินของลูกค้าไม่เพียงพอ', 'state' => 'error');
                            return json_encode($arr);
                        } else {
                            $wallet_after = $wallet_before - $data['amount'];

                            if ($bonus > 1) {

                                $i = DB::table('bonus_settings')->where('SL_FUNC', $bonus)->get()->first();

                                if ($i) {
                                    $SL_ID = $i->ID;
                                    $SL_TURNOVER = $i->SL_TURNOVER;
                                    $SL_BONUS = $i->SL_BONUS;
                                    $SL_TITLE = $i->SL_TITLE;
                                    $SL_MINIMUM = $i->SL_MINIMUM;
                                    $SL_MAXBONUS = $i->SL_MAXBONUS;
                                    $SL_OEM = $i->SL_OEM;
                                } else {
                                    $arr = array('message' => 'ไม่สามารถโยกเงินได้คะ เนื่องจากระบบโบนัสมีปัญหาอย่างร้ายแรง กรุณาติดต่อแอดมินคะ', 'state' => 'error');
                                    return json_encode($arr);
                                }

                                if (!$i && $bonus != 1) {
                                    $arr = array('message' => 'ไม่มีโบนัสที่ต้องการ', 'state' => 'error');
                                    return json_encode($arr);
                                }

                                //เช็คการรับโบนัสในวันนั้นๆ โปรโมชั่นเติม 19 ได้ 99
                                if ($i->SL_OEM == 1) {

                                    $f = DB::table('logs_transferlog')
                                        ->where('CustomerID', $customerid)
                                        ->where('BONUS_ID', $i->ID) //ไอดีโปรโมชั่นเติม 19 ได้ 99
                                        ->whereNotNull('TURNOVER_ID')
                                        ->whereDate('WITHDRAW_DATETIME', date('Y-m-d'))
                                        ->orderBy('WITHDRAW_TXID', 'desc')
                                        ->get()->first();

                                    if ($f) {
                                        $arr = array('message' => 'ไม่สามารถรับโบนัสได้ เนื่องจากคุณได้รับโบนัสวันนี้ไปแล้ว', 'state' => 'error');
                                        return json_encode($arr);
                                    }
                                }

                                //เช็คการรับโบนัส จาก id 6 - 13 สามารถรับได้ครั้งเดียว
                                if ($i->SL_OEM == 2) {
                                    $onebonus = DB::table('logs_transferlog')
                                        ->where('CustomerID', $customerid)
                                        ->where('BONUS_ID', $i->ID)
                                        ->whereNotNull('TURNOVER_ID')
                                        ->orderBy('WITHDRAW_TXID', 'desc')
                                        ->get()->first();

                                    if ($onebonus) {
                                        $arr = array('message' => 'ไม่สามารถรับโบนัสได้ เนื่องจากคุณได้รับโบนัสนี้ไปแล้ว', 'state' => 'error');
                                        return json_encode($arr);
                                    }
                                }

                                /////// start
                                if ($SL_OEM == 0) {
                                    if ($bonus == 3) { //สมาชิกใหม่รับโบนัสทันที 100%
                                        $j = DB::table('logs_transferlog')->where('CustomerID', $customerid)->where('WITHDRAW_STATUS', 2)->get()->first();

                                        if ($j) {
                                            $arr = array('message' => 'ไม่สามารถรับโบนัสได้ เนื่องจากมียอดเล่นเกมส์ไปแล้ว', 'state' => 'error');
                                            return json_encode($arr);
                                        }
                                    } else if ($bonus == 4) { //ไม่มียอดฝากภายใน 7 วัน รับฟรี!
                                        $j = DB::table('logs_transferlog')->where('CustomerID', $customerid)->where('WITHDRAW_STATUS', 2)->orderBy('WITHDRAW_DATETIME', 'desc')->get()->first();
                                        $WITHDRAW_DATETIME = $j->WITHDRAW_DATETIME;

                                        $today = date("Ymd");
                                        $newDate = date("Ymd", strtotime($WITHDRAW_DATETIME));
                                        $dat7day = $today - $newDate;

                                        if ($dat7day < 7) {
                                            $arr = array('message' => 'ไม่สามารถรับโบนัสได้ เนื่องจากลูกค้ามียอดฝากภายใน 7 วัน', 'state' => 'error');
                                            return json_encode($arr);
                                        }
                                    } else if ($bonus == 5) {
                                        $j = DB::table('logs_transferlog')->where('CustomerID', $customerid)->where('WITHDRAW_STATUS', 2)->where('BONUS_ID', $SL_ID)->get()->first();
                                        if ($j) {
                                            $arr = array('message' => 'ไม่สามารถรับโบนัสได้ เนื่องจากลูกค้าได้รับโบนัสนี้ไปแล้ว', 'state' => 'error');
                                            return json_encode($arr);
                                        }
                                    }

                                    if ($data['amount'] < $SL_MINIMUM) {
                                        $arr = array('message' => 'ไม่สามารถย้ายเงินได้คะ จำเป็นต้องย้ายเงินขั้นต่ำ ' . $SL_MINIMUM . ' บาท', 'state' => 'error');
                                        return json_encode($arr);
                                    }

                                    $bonuscredit = ($data['amount'] * $SL_BONUS) / 100;

                                    if ($bonuscredit > $SL_MAXBONUS) {
                                        $bonuscredit = $SL_MAXBONUS;
                                    }

                                    $totalcredit = $data['amount'] + $bonuscredit;
                                    $TURN_AMOUNT = $totalcredit * $SL_TURNOVER;

                                    $bonus_message = 'รับโบนัสเพิ่มจำนวน ' . $bonuscredit . ' บาท ต้องทำยอดเงินคงเหลือให้ถึง ' . $TURN_AMOUNT . ' บาท จึงจะสามารถถอนได้';
                                } else {
                                    // โปรโมชั่น ตามที่กำหนด เช่น 19 ได้ 99
                                    if ($data['amount'] != $SL_MINIMUM) {
                                        $arr = array('message' => 'ไม่สามารถย้ายเงินได้คะ จำเป็นต้องย้ายเงิน ' . $SL_MINIMUM . ' บาทเท่านั้น', 'state' => 'error');
                                        return json_encode($arr);
                                    }

                                    $totalcredit = $SL_MAXBONUS; //ยอดที่ได้รับ
                                    $TURN_AMOUNT = $SL_TURNOVER; //เทรินโอเวิอร์

                                    $bonus_message = 'รับเงินโบนัส ' . $totalcredit . ' บาท ต้องทำยอดเงินคงเหลือให้ถึง ' . $TURN_AMOUNT . ' บาท จึงจะสามารถถอนได้';
                                }
                                /////// end

                            } else {
                                // ไม่รับโบนัส
                                $totalcredit = $data['amount'];
                            }

                            $pinsert = array(
                                'CustomerID' => $customerid,
                                'WITHDRAW_TYPE' => config('app.AG_AGENT'),
                                'WITHDRAW_AMOUNT' => $data['amount'],
                                'WITHDRAW_BEFORE' => $wallet_before,
                                'WITHDRAW_AFTER' => $wallet_after,
                                'WITHDRAW_DATETIME' => date('Y-m-d H:i:s'),
                                'REFID' => $data['refId']
                            );

                            DB::table('logs_transferlog')->insert($pinsert);

                            $k = DB::table('logs_transferlog')
                                ->where('CustomerID', $customerid)
                                ->where('WITHDRAW_STATUS', 0)
                                ->orderBy('WITHDRAW_TXID', 'desc')
                                ->get()->first();


                            if ($k) {

                                $txid = $k->WITHDRAW_TXID;

                                DB::table('logs_transferlog')->where('WITHDRAW_TXID', $txid)->update(['WITHDRAW_STATUS' => $DEPOSIT_TOTAL]);

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                                curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                                $form_field = array();
                                $form_field['username'] = $SLOT_USERNAME;
                                $form_field['amount'] = $totalcredit;
                                $post_string = '';

                                foreach ($form_field as $key => $value) {
                                    $post_string .= $key . '=' . urlencode($value) . '&';
                                }

                                $post_string = substr($post_string, 0, -1);

                                curl_setopt($ch, CURLOPT_URL, 'https://connect.mafia88.club/?agent=' . config('app.AG_AGENT') . '&method=tc');
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);

                                $response = curl_exec($ch);
                                curl_close($ch);
                                $json = json_decode($response);

                                if ($json->{'result'} == 'ok') {

                                    $credit_balance = $json->{'balance'};
                                    // $credit_balance = 99;

                                    if ($bonus > 1) {

                                        DB::table('logs_transferlog')->where('WITHDRAW_TXID', $txid)->update(['WITHDRAW_STATUS' => 2, 'BONUS_ID' => $SL_ID]);

                                        $tinsert = array(
                                            'CustomerID' => $customerid,
                                            'WITHDRAW_TXID' => $txid,
                                            'CREDIT_AMOUNT' => $totalcredit,
                                            'TURN_AMOUNT' => $TURN_AMOUNT,
                                            'TURN_DATETIME' => date('Y-m-d H:i:s'),
                                        );

                                        DB::table('credit_turnover')->insert($tinsert);

                                        $l = DB::table('credit_turnover')->where('CustomerID', $customerid)->where('WITHDRAW_TXID', $txid)->get()->first();

                                        if ($l) {
                                            $turn_id = $l->ID;

                                            DB::table('logs_transferlog')->where('WITHDRAW_TXID', $txid)->update(['TURNOVER_ID' => $turn_id]);
                                        }
                                    } else {
                                        DB::table('logs_transferlog')->where('WITHDRAW_TXID', $txid)->update(['WITHDRAW_STATUS' => 2]);
                                    }

                                    if ($SL_STATUS != 'vip') {

                                        DB::table('account_users')->where('CustomerID', $customerid)->update(['SL_STATUS' => 'vip']);

                                        $yinsert = array(
                                            'CustomerID' => $customerid,
                                            'SL_STATUS' => 'vip',
                                            'DATETIME' => date('Y-m-d H:i:s'),
                                        );

                                        DB::table('account_statuslog')->insert($yinsert);
                                    }
                                } else {
                                    DB::table('logs_transferlog')->where('WITHDRAW_TXID', $k->WITHDRAW_TXID)->update(['WITHDRAW_STATUS' => 3]);
                                    $credit_balance = null;
                                    $arr = array('message' => 'การโยกเงินผิดพลาด เนื่องจากระบบปิดปรับปรุงชั่วคราว กรุณารอสักครู่ (1)', 'state' => 'error', 'code' => '102');
                                    return json_encode($arr);
                                }

                                if ($bonus > 1) {
                                    $jbosut = $SL_TITLE;
                                } else {
                                    $jbosut = "ไม่รับโบนัสและโปรโมชั่น";
                                }

                                $form_field = array();
                                $form_field['message'] = "\nโยกเงิน " . number_format($totalcredit, 2) . " บาท\n\nUsername: " . strtoupper($SLOT_USERNAME) . "\nหมายเลขโทรศัพท์: " . $SL_USERNAME . "\nเวลาที่โยกเงิน: " . date('Y-m-d H:i:s') . " น.\n\nชื่อ นามสกุล: " . $SL_FIRSTNAME . " " . $SL_LASTNAME . "\nหมายเลขบัญชี: " . $SL_BANKID . "\nโบนัส: " . $jbosut;
                                $post_string = '';

                                foreach ($form_field as $key => $value) {
                                    $post_string .= $key . '=' . urlencode($value) . '&';
                                }

                                $post_string = substr($post_string, 0, -1);

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, 'https://notify-api.line.me/api/notify');
                                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Authorization: Bearer ' . config('app.AG_LINE_DEPOSIT')]);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                curl_setopt($ch, CURLOPT_HEADER, 0);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
                                $data = curl_exec($ch);
                                curl_close($ch);

                                if ($bonus > 1) {
                                    $arr = array('message' => $bonus_message, 'credit' => number_format($credit_balance, 2), 'state' => 'success');
                                    return json_encode($arr);
                                } else {
                                    $arr = array('message' => 'ย้ายเงินเข้าเกมสำเร็จ จำนวนเงิน ' . number_format($totalcredit, 2) . ' บาท', 'credit' => number_format($credit_balance, 2), 'state' => 'success');
                                    return json_encode($arr);
                                }
                            }
                        }
                    }
                }
            } else {
                $arr = array('message' => 'การโยกเงินผิดพลาด เนื่องจากระบบปิดปรับปรุงชั่วคราว กรุณารอสักครู่', 'state' => 'error', 'code' => '101');
                return json_encode($arr);
            }
        } else {
            $arr = array('message' => 'ไม่พบบัญชีของลูกค้า', 'state' => 'error');
            return json_encode($arr);
        }
    }
}
