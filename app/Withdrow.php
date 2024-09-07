<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Withdrow extends Model
{

    public static function withdrow($data)
    {

        $io = DB::table('account_session')->where('SL_SESSION', $data['token'])->where('SL_LOGINIP', $data['ip'])->get()->first();

        if ($io) {

            $customerid = $io->CustomerID;

            $a = DB::table('credit_withdrawlog')->where('CustomerID', $customerid)->where('WITHDRAW_STATUS', 1)->get()->first();

            if ($a) {
                return 'ไม่สามารถถอนได้! มีรายการถอนกำลังรอดำเนินการ';
            }

            $b = DB::table('account_users')->where('CustomerID', $customerid)->get()->first();

            if ($b) {
                $SLOT_USERNAME = $b->SLOT_USER;
                $SL_USERNAME = $b->SL_USERNAME;
                $SL_FIRSTNAME = $b->SL_FIRSTNAME;
                $SL_LASTNAME = $b->SL_LASTNAME;
                $SL_BANKID = $b->SL_BANKID;
                $BANK_ID = $b->SL_BANK_ID;
                $SL_STATUS = $b->SL_STATUS;

                if ($SL_STATUS == 'free') {
                    $freecredit_user = 1;
                } else {
                    $freecredit_user = 0;
                }
            } else {
                return 'ไม่พบยูสเซอร์';
            }

            $refId = DB::table('credit_withdrawlog')->where('REFID', $data['refId'])->get()->first();

            if($refId){
                $arr = array('message' => 'ไม่สามารถถอนเงินได้ เลขรายการซ้ำกัน', 'state' => 'error');
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
                $credit_before = str_replace(",", "", $json->{'balance'});
                // $credit_before = 2000;

                if ($credit_before < 300) {
                    return 'ลูกค้าจำเป็นต้องถอนเงินขั้นต่ำ 300 บาท';
                } else {
                    if ($freecredit_user == 1) {
                        if ($credit_before < 500) {
                            return 'ท่านติดสถานะเครดิตฟรี จำเป็นต้องถอนขั้นต่ำ 500.00 บาท';
                        }
                    }

                    if ($credit_before < 5) {
                        DB::table('credit_turnover')->where('CustomerID', $customerid)->where('TURN_STATUS', 0)->update(['TURN_STATUS' => 1]);
                    }
                    if ($freecredit_user == 1 && $credit_before < 500) {
                        return 'ท่านมียอดเงินคงเหลือไม่เพียงพอ จำเป็นต้องมียอดเงินคงเหลืออย่างน้อย 500.00 บาท';
                    }

                    if ($freecredit_user == 0 && $credit_before < 300) {
                        return 'ยอดเงินคงเหลือต่ำกว่า 300.00 ไม่สามารถถอนได้ ตอนนี้ท่านมี ' . number_format($credit_before, 2) . ' บาท';
                    }

                    if ($freecredit_user != 1) {

                        $d = DB::table('logs_transferlog')
                            ->where('CustomerID', $customerid)
                            ->orderBy('WITHDRAW_TXID', 'desc')
                            ->get()->first();
                        if ($d) {
                            $TURNOVER_ID = $d->TURNOVER_ID;
                            $WITHDRAW_AMOUNT = $d->WITHDRAW_AMOUNT;

                            if ($TURNOVER_ID == null || $TURNOVER_ID == '') {
                                if ($WITHDRAW_AMOUNT == $credit_before) {
                                    return 'ลูกค้าจำเป็นต้องมีรายการเล่นอย่างน้อย 1 ครั้ง';
                                }
                            } else {

                                $f = DB::table('credit_turnover')->where('ID', $TURNOVER_ID)->get()->first();

                                if ($f) {
                                    $TURN_AMOUNT = $f->TURN_AMOUNT;

                                    if ($TURN_AMOUNT > $credit_before) {
                                        return 'ลูกค้าต้องทำยอดคงเหลือให้ถึง ' . number_format($TURN_AMOUNT, 2) . ' บาท';
                                    }
                                } else {
                                    return 'ไม่สามารถเช็คยอดเทรินได้';
                                }
                            }
                        }
                    }

                    if ($credit_before < 300) {
                        return 'เครดิตของลูกค้าไม่เพียงพอสำหรับการถอน';
                    } else {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                        $form_field = array();
                        $form_field['username'] = $SLOT_USERNAME;
                        $form_field['amount'] = '-' . $credit_before;
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
                            $credit_after = $json->{'balance'};
                            // $credit_after = 0;

                            if ($freecredit_user == 1) {
                                $credit_before = 50;
                            } else {

                                $trasnfer = DB::table('logs_transferlog')->where('CustomerID', $customerid)->orderBy('WITHDRAW_DATETIME', 'desc')->get()->first();
                                if ($trasnfer) {
                                    if ($trasnfer->BONUS_ID == 0) {
                                        $credit_before = $credit_before;
                                    } else {
                                        if ($trasnfer->BONUS_ID > 4) {
                                            $i = DB::table('bonus_settings')->where('ID', $trasnfer->BONUS_ID)->get()->first();
                                            if ($i) {
                                                if ($i->SL_WITHDRAW == 0) {
                                                    $credit_before = $credit_before;
                                                } else {
                                                    $credit_before = $i->SL_WITHDRAW;
                                                }
                                            } else {
                                                return 'ไม่สามารถถอนเงินได้คะ เนื่องจากไม่พบโบนัสในระบบ กรุณาติดต่อแอดมินคะ';
                                            }
                                        }
                                    }
                                }
                            }
                            
                            $oinsert = array(
                                'CustomerID' => $customerid,
                                'WITHDRAW_AMOUNT' => $credit_before,
                                'WITHDRAW_BEFORE' => $credit_before,
                                'WITHDRAW_AFTER' => $credit_after,
                                'WITHDRAW_DATETIME' => date('Y-m-d H:i:s'),
                                'WITHDRAW_STATUS' => 0,
                                'REFID' => $data['refId']
                            );

                            DB::table('credit_withdrawlog')->insert($oinsert);

                            $g = DB::table('credit_withdrawlog')
                                ->where('CustomerID', $customerid)
                                ->where('WITHDRAW_STATUS', 0)
                                ->orderBy('WITHDRAW_TXID', 'desc')
                                ->get()->first();

                            if ($g) {
                                $txid = $g->WITHDRAW_TXID;
                                $WITHDRAW_DATETIME = $g->WITHDRAW_DATETIME;

                                DB::table('credit_turnover')->where('CustomerID', $customerid)->where('TURN_STATUS', 0)->update(['TURN_STATUS' => 1]);
                                DB::table('credit_withdrawlog')->where('WITHDRAW_TXID', $txid)->update(['WITHDRAW_STATUS' => 1]);

                                $h = DB::table('bank_information')->where('BANK_ID', $BANK_ID)->get()->first();
                                if ($h) {
                                    $BANK_NAME = $h->BANK_NAME;
                                }

                                $form_field = array();
                                $form_field['message'] = number_format($credit_before, 2) . " บาท\n\nUsername: " . strtoupper($SLOT_USERNAME) . "\nหมายเลขโทรศัพท์: " . $SL_USERNAME . "\nเวลาที่แจ้งถอน: " . $WITHDRAW_DATETIME . " น.\n\nชื่อ นามสกุล: " . $SL_FIRSTNAME . " " . $SL_LASTNAME . "\nธนาคาร: " . $BANK_NAME . "\nหมายเลขบัญชี: " . $SL_BANKID;
                                $post_string = '';

                                foreach ($form_field as $key => $value) {
                                    $post_string .= $key . '=' . urlencode($value) . '&';
                                }

                                $post_string = substr($post_string, 0, -1);

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, 'https://notify-api.line.me/api/notify');
                                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Authorization: Bearer ' . config('app.AG_LINE_WITHDRAW')]);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                curl_setopt($ch, CURLOPT_HEADER, 0);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
                                $data = curl_exec($ch);
                                curl_close($ch);

                                // DB::table('account_slot')->where('CustomerID', $customerid)->update(['SLOT_BALANCE' => $credit_after]);
                                return 1;
                            } else {
                                return 'มีอะไรบางอย่างผิดพลาด';
                            }
                        } else {
                            return 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ กรุณาติดต่อแอดมิน';
                        }
                    }
                }
            } else {
                return 'ไม่สามารถเช็คเครดิตได้ กรุณาลองอีกครั้งหรือติดต่อแอดมิน';
            }
        } else {
            return 'ไม่พบบัญชีของลูกค้า';
        }
    }
}
