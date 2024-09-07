<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Auth extends Model
{

    public static function Truewallet($data)
    {
        $bank = DB::table('bank_setting')->where('bank_type', 22)->where('bank_status', 0)->get()->first();
        if ($bank) {
            $user = DB::table('account_users')->where('CustomerID', $data['customerid'])->get()->first();

            if ($user) {

                $SL_USERNAME = $user->SL_USERNAME;
                $CustomerID = $user->CustomerID;
                $SL_BANKID = $user->SL_BANKID;
                $SLOT_USER = $user->SLOT_USER;

                // $replace_gift = "R1k182c7iSnbPpbl1c";
                $replace_gift = str_replace('https://gift.truemoney.com/campaign/?v=', '', $data['gift']);

                if (!$replace_gift) {
                    $arr = array('message' => 'ลิ้งค์ของขวัญไม่ถูกต้อง', 'state' => 'error');
                    return json_encode($arr);
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout in seconds
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_URL, 'https://gift.truemoney.com/campaign/vouchers/' . $replace_gift . '/verify?mobile=' . $bank->bank_number);

                $response = curl_exec($ch);
                $json = json_decode($response);

                if ($json->status->code == "VOUCHER_NOT_FOUND") {
                    $arr = array('message' => 'ไม่พบซองของขวัญนี้!', 'state' => 'error');
                    curl_close($ch);
                    return json_encode($arr);
                } else if ($json->status->code == "TARGET_USER_REDEEMED" || $json->status->code == "VOUCHER_OUT_OF_STOCK") {
                    $arr = array('message' => 'ซองของขวัญนี้ถูกใช้ไปแล้ว!', 'state' => 'error');
                    curl_close($ch);
                    return json_encode($arr);
                } else if ($json->status->code == "SUCCESS") {

                    $postRequest = array(
                        "mobile" => $bank->bank_number,
                        "voucher_hash" => $replace_gift
                    );

                    curl_setopt($ch, CURLOPT_URL, 'https://gift.truemoney.com/campaign/vouchers/' . $replace_gift . '/redeem');
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postRequest));

                    $headers[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    $response = curl_exec($ch);
                    $json = json_decode($response);

                    if ($json->status->code != "SUCCESS") {
                        $arr = array('message' => 'ไม่สามารถเติมเงินได้!', 'state' => 'error');
                        curl_close($ch);
                        return json_encode($arr);
                    } else {

                        $amount = str_replace(',', '', number_format($json->data->voucher->amount_baht, 2));
                        // $amount = 10;

                        $oinsert = array(
                            'DEPOSIT_WALLET_ID' => $replace_gift,
                            'DEPOSIT_CLIENTID' => $SL_USERNAME,
                            'DEPOSIT_AMOUNT' => $amount,
                            'DEPOSIT_DATE' => date('Y-m-d H:i:s'),
                            'DEPOSIT_OWNER' => $bank->bank_number,
                            'DEPOSIT_OWNERCODE' => "WALLET",
                            'DEPOSIT_BANKCODE' => "WALLET",
                            'DEPOSIT_TXSTATUS' => 2,
                        );

                        DB::table('bank_transaction')->insert($oinsert);
                    
                        $logs_balance = DB::table('logs_balance')->where('CustomerID', $CustomerID)->get()->first();

                        if (!$logs_balance) {

                            $bls = array(
                                'CustomerID' => $CustomerID,
                            );
                            DB::table('logs_balance')->insert($bls);
                            $BALANCE_BEFORE = 0;
                        } else {

                            $BALANCE_BEFORE = $logs_balance->SL_BALANCE;
                        }


                        DB::table('logs_balance')->where('CustomerID', $CustomerID)->update([
                            'SL_DEPOSIT' => DB::raw('SL_DEPOSIT + '.$amount),
                            'SL_BALANCE' => DB::raw('SL_BALANCE + '.$amount),
                        ]);


                        $balance2 = DB::table('logs_balance')->where('CustomerID', $CustomerID)->get()->first();

                        $BALANCE_AFTER = $balance2->SL_BALANCE;

                        if ($BALANCE_BEFORE != $BALANCE_AFTER && $BALANCE_BEFORE < $BALANCE_AFTER) {

                            $deposit = array(
                                'CustomerID' => $CustomerID,
                                'DEPOSIT_TYPE' => "wallet",
                                'DEPOSIT_TXID' => $replace_gift,
                                'DEPOSIT_AMOUNT' => $amount,
                                'WALLET_BEFORE' => $BALANCE_BEFORE,
                                'WALLET_AFTER' => $BALANCE_AFTER,
                                'DEPOSIT_DATETIME' => date('Y-m-d H:i:s'),
                                'DEPOSIT_STATUS' => 2,
                            );

                            DB::table('logs_depositlog')->insert($deposit);

                            $form_field = array();
                            $form_field['message'] = $amount . " บาท\n\nBANKID: " . $SL_BANKID . "\nUsername: " . strtoupper($SLOT_USER) . "\nหมายเลขโทรศัพท์: " . $SL_USERNAME . "\nเวลาที่ฝากเงิน: " . date('Y-m-d H:i:s') . " น.\nวอลเล็ทซองของขวัญ: " . $bank->bank_number . "\nโค้ดซองของขวัญ: " . $replace_gift;
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
                            curl_exec($ch);

                            $arr = array('message' => 'เติมเงินสำเร็จ จำนวน ' . $amount . '!', 'state' => 'success');
                            curl_close($ch);
                            return json_encode($arr);
                        } else {
                            $arr = array('message' => 'มีบางอย่างผิดพลาดกรุณาติดต่อแอดมิน!', 'state' => 'error');
                            return json_encode($arr);
                        }
                    }
                }
            } else {
                $arr = array('message' => 'ไม่พบยูสเซอร์', 'state' => 'error');
                return json_encode($arr);
            }
        } else {
            $arr = array('message' => 'แจ้งปิดระบบฝากเงินช่องทาง TrueMoney Wallet ชั่วคราว', 'state' => 'error');
            return json_encode($arr);
        }
    }
    public static function registerUser($data)
    {

        $flag = DB::table('account_users')->where('SL_USERNAME', $data['SL_USERNAME'])->get()->first();

        if (!$flag) {

            $flag1 = DB::table('account_users')->where('SL_FIRSTNAME', $data['SL_FIRSTNAME'])->where('SL_LASTNAME', $data['SL_LASTNAME'])->get()->first();
            if ($flag1) {
                return 2;
            } else {

                $oc = DB::table('bank_information')->where('BANK_CODE_X', $data['SL_BANK_ID'])->get()->first();
                if (!$oc) {
                    return 5;
                }

                if ($oc->BANK_ID == 1) {

                    $flag2 = DB::table('account_users')->where('SL_BANK_ID', 1)->where('SL_BANKID', 'like', '%' . substr($data['SL_BANKID'], -4))->get();
                    if (!empty($flag2[0]->SL_BANKID)) {
                        return 3;
                    }
                } else {

                    $flag3 = DB::table('account_users')->where('SL_BANK_ID', $oc->BANK_ID)->where('SL_BANKID', 'like', '%' . substr($data['SL_BANKID'], -6))->get();
                    if (!empty($flag3[0]->SL_BANKID)) {
                        return 3;
                    }
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $form_field = array();
                $form_field['username'] = $data['SLOT_USER'];
                $form_field['password'] = $data['SL_PASSWORD'];
                $post_string = '';

                foreach ($form_field as $key => $value) {
                    $post_string .= $key . '=' . urlencode($value) . '&';
                }

                $post_string = substr($post_string, 0, -1);

                curl_setopt($ch, CURLOPT_URL, 'https://mftx.slotxo-api.com/?agent=' . config('app.AG_AGENT') . '&method=cu');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);

                $response = curl_exec($ch);
                
                curl_close($ch);
                $json = json_decode($response);

                if ($json->{'result'} == 'ok') {
                    // Register Success

                    $oinsert = array(
                        'SLOT_USER' => $data['SLOT_USER'],
                        'SL_USERNAME' => $data['SL_USERNAME'],
                        'SL_PASSWORD' => md5($data['SL_PASSWORD']),
                        'SL_FIRSTNAME' => $data['SL_FIRSTNAME'],
                        'SL_LASTNAME' => $data['SL_LASTNAME'],
                        'SL_REGISIP' => $data['SL_REGISIP'],
                        'SL_REGISDATE' => $data['SL_REGISDATE'],
                        'SL_BANK_ID' => $oc->BANK_ID,
                        'SL_BANKID' => $data['SL_BANKID'],
                        'SL_LINEID' => $data['SL_LINEID'],
                        'SL_FREECODE' => $data['SL_FREECODE'],
                        'SL_REFERER' => $data['SL_REFERER'],
                        'SL_SEX' => $data['SL_SEX'],
                        'SL_AGE' => $data['SL_AGE'],
                    );

                    DB::table('account_users')->insert($oinsert);

                    return 1;
                } else {
                    return 4;
                }
            }
        } else {
            return 0;
        }
    }

    public static function checkUser($data)
    {
        $flag = DB::table('account_users')->where('SL_USERNAME', $data['username'])->get()->first();
        if ($flag) {
            $arr = array('message' => 'This phone number is already in use.', 'state' => 'error'); //เบอร์โทรศัพท์นี้มีนระบบแล้ว
            return json_encode($arr);
        } else {
            $arr = array('message' => 'This phone number can be used.', 'state' => 'success');
            return json_encode($arr); //เบอร์โทรศัพท์นี้สามารถใช้งานได้
        }
    }

    public static function login($data)
    {
        $flag = DB::table('account_users')->where('SL_USERNAME', $data['username'])->where('SL_PASSWORD', md5($data['password']))->get()->first();
        // var_dump($flag);die();
        if ($flag) {
            $customerid = $flag->CustomerID;
            $sl_sessionsha1 = sha1($data['ip'] . '|' . date('Y-m-d H:i:s') . '|' . $data['password']);

            $sl_session = DB::table('account_session')->where('CustomerID', $customerid)->get()->first();

            if ($sl_session) {
                DB::table('account_session')->where('CustomerID', $customerid)->update(['SL_SESSION' => $sl_sessionsha1, 'SL_LOGINIP' => $data['ip'], 'DATE' => date('Y-m-d H:i:s')]);
            } else {
                $oinsert = array(
                    'CustomerID' => $customerid,
                    'SL_SESSION' => $sl_sessionsha1,
                    'DATE' => date('Y-m-d H:i:s'),
                    'SL_LOGINIP' => $data['ip'],
                );
                DB::table('account_session')->insert($oinsert);
            }

            DB::table('account_users')->where('CustomerID', $customerid)->update(['SL_LASTLOGIN' => date('Y-m-d H:i:s')]);

            $d = DB::table('bank_information')->where('BANK_ID', $flag->SL_BANK_ID)->get()->first();
            if ($d) {
                $bankname = $d->BANK_NAME;
                $bankcode = $d->BANK_CODE;
            } else {
                $bankname = null;
                $bankcode = null;
            }


            $arr = array(
                'message' => 'Login Successfully!',
                'token' => $sl_sessionsha1,
                'password' => $data['password'],
                'ip' => $data['ip'],
                'uid' => $customerid,
                'user' => $flag->SLOT_USER,
                'username' => $flag->SL_USERNAME,
                'firstname' => $flag->SL_FIRSTNAME,
                'lastname' => $flag->SL_LASTNAME,
                'bankname' => $bankname,
                'bankid' => $flag->SL_BANKID,
                'bankcode' => $bankcode,
                'regisdate' => $flag->SL_REGISDATE,
                'state' => 'success'
            );
            return json_encode($arr);
        } else {
            $arr = array('message' => 'Username หรือ Password ผิดพลาด โปรดตรวจสอบข้อมูล', 'state' => 'error');
            return json_encode($arr);
        }
    }

    public static function getCredit($data)
    {
        $io = DB::table('account_session')->where('SL_SESSION', $data['token'])->where('SL_LOGINIP', $data['ip'])->get()->first();

        if ($io) {
            $customerid = $io->CustomerID;

            $b = DB::table('account_users')->where('CustomerID', $customerid)->get()->first();

            if ($b) {
                $SLOT_USERNAME = $b->SLOT_USER;

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
                        DB::table('credit_turnover')->where('CustomerID', $customerid)->update(['TURN_STATUS' => 1]);
                    }
                    $arr = array('message' => $json->{'balance'}, 'state' => 'success');
                    return json_encode($arr);
                } else {
                    $arr = array('message' => 'ระบบมีปัญหา ไม่สามารถเช็คเครดิตได้', 'state' => 'error');
                    return json_encode($arr);
                }
            } else {
                $arr = array('message' => 'ไม่พบยูสเซอร์', 'state' => 'error');
                return json_encode($arr);
            }
        }
    }

    public static function getProfile($data)
    {

        $io = DB::table('account_session')->where('SL_SESSION', $data['token'])->where('SL_LOGINIP', $data['ip'])->get()->first();

        if ($io) {
            $customerid = $io->CustomerID;

            $b = DB::table('account_users')->where('CustomerID', $customerid)->get()->first();

            if ($b) {

                $firstname = $b->SL_FIRSTNAME;
                $lastname = $b->SL_LASTNAME;
                $bankid = $b->SL_BANKID;
                $lineid = $b->SL_LINEID;
                $regisdate = $b->SL_REGISDATE;
                $SL_USERNAME = $b->SL_USERNAME;
                $SLOT_USER = $b->SLOT_USER;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300); //timeout in seconds
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $form_field = array();
                $form_field['username'] = $SLOT_USER;
                $post_string = '';

                foreach ($form_field as $key => $value) {
                    $post_string .= $key . '=' . urlencode($value) . '&';
                }

                $post_string = substr($post_string, 0, -1);

                curl_setopt($ch, CURLOPT_URL, 'https://mftx.slotxo-api.com/?agent=' . config('app.AG_AGENT') . '&method=gc');
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

                    $DEPOSIT_TOTAL = 0;
                    $TRANSFER_TOTAL = 0;
                    $WITHDRAW_TOTAL = 0;
                    //เช็คยอดฝาก
                    $aa = DB::table('logs_depositlog')->distinct()->select('DEPOSIT_TXID')->where('CustomerID', $customerid)->groupBy('DEPOSIT_TXID')->get();

                    foreach ($aa as $res) {
                        $bb = DB::table('logs_depositlog')->where('DEPOSIT_TXID', $res->DEPOSIT_TXID)->where('CustomerID', $customerid)->get()->first();

                        $DEPOSIT_TOTAL = $DEPOSIT_TOTAL + $bb->DEPOSIT_AMOUNT;
                    }

                    //เช็คยอดโยกย้าย
                    $cc = DB::table('logs_transferlog')->distinct()->select('WITHDRAW_TXID')->where('CustomerID', $customerid)->where('WITHDRAW_STATUS', 2)->groupBy('WITHDRAW_TXID')->get();

                    foreach ($cc as $res) {
                        $d = DB::table('logs_transferlog')->where('WITHDRAW_TXID', $res->WITHDRAW_TXID)->where('CustomerID', $customerid)->get()->first();

                        $TRANSFER_TOTAL = $TRANSFER_TOTAL + $d->WITHDRAW_AMOUNT;
                    }

                    //เช็คยอดถอน
                    $ccc = DB::table('credit_withdrawlog')->distinct()->select('WITHDRAW_TXID')->where('CustomerID', $customerid)->where('WITHDRAW_STATUS', 2)->groupBy('WITHDRAW_TXID')->get();

                    foreach ($ccc as $res) {
                        $ddd = DB::table('credit_withdrawlog')->where('WITHDRAW_TXID', $res->WITHDRAW_TXID)->where('CustomerID', $customerid)->get()->first();

                        $WITHDRAW_TOTAL = $WITHDRAW_TOTAL + $ddd->WITHDRAW_AMOUNT;
                    }

                    $ee = DB::table('logs_balance')->where('CustomerID', $customerid)->get()->first();

                    if ($ee) {

                        $OLD_BALANCE = $ee->SL_BALANCE_OLD;

                        $WALLET_BALANCE = ($OLD_BALANCE + $DEPOSIT_TOTAL) - $TRANSFER_TOTAL;

                        if ($WALLET_BALANCE < 0) {
                            $WALLET_BALANCE = 0;
                        }

                        DB::table('logs_balance')->where('CustomerID', $customerid)->update(['SL_DEPOSIT' => $DEPOSIT_TOTAL, 'SL_WITHDRAW' => $TRANSFER_TOTAL, 'SL_BALANCE' => $WALLET_BALANCE]);
                    } else {

                        $oinsert = array(
                            'CustomerID' => $customerid,
                            'SL_DEPOSIT' => 0,
                            'SL_WITHDRAW' => 0,
                            'SL_BALANCE' => 0,
                        );

                        DB::table('logs_balance')->insert($oinsert);

                        $WALLET_BALANCE = 0;
                    }

                    $d = DB::table('bank_information')->where('BANK_ID', $b->SL_BANK_ID)->get()->first();
                    if ($d) {
                        $bankname = $d->BANK_NAME;
                        $bankcode = $d->BANK_CODE;
                    } else {
                        $bankname = null;
                        $bankcode = null;
                    }

                    $g = DB::table('credit_turnover')
                        ->where('CustomerID', $customerid)
                        ->where('TURN_STATUS', 0)
                        ->orderBy('ID', 'desc')
                        ->get()->first();

                    if (!empty($g)) {
                        $turn = $g->TURN_AMOUNT;
                    } else {
                        $turn = 0;
                    }

                    $arr = array(
                        'slotuser' => $SLOT_USER,
                        'username' => $SL_USERNAME,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'bankname' => $bankname,
                        'bankid' => $bankid,
                        'lineid' => $lineid,
                        'regisdate' => $regisdate,
                        'bankcode' => strtolower($bankcode),
                        'balance' => number_format($WALLET_BALANCE, 2),
                        'credit' => number_format($credit_balance, 2),
                        'turnover' => $turn,
                        'total_deposit' => number_format($DEPOSIT_TOTAL, 2),
                        'total_withdraw' => number_format($WITHDRAW_TOTAL, 2),
                        'state' => 'success',
                    );
                    return json_encode($arr);
                } else {
                    $arr = array('message' => 'Unable to connect to the server. Please contact admin', 'state' => 'error'); //ไม่สามารถเชื่อมจ่อกับเซริฟ์เวอร์ได้ กรุณาติดต่อแอดมิน
                    return json_encode($arr);
                }
            }
        } else {
            $arr = array('message' => 'User not found', 'state' => 'error'); //ไม่พบยูสเซอร์
            return json_encode($arr);
        }
    }

    public static function getCoupon($data)
    {

        $io = DB::table('account_session')->where('SL_SESSION', $data['token'])->where('SL_LOGINIP', $data['ip'])->get()->first();

        if ($io) {
            $customerid = $io->CustomerID;

            $b = DB::table('account_users')->where('CustomerID', $customerid)->get()->first();

            if ($b) {

                $SLOT_USER = $b->SLOT_USER;
                $SL_FREECODE = $b->SL_FREECODE;
                $SL_STATUS = $b->SL_STATUS;

                $code = DB::table('table_coupon')->where('CODE', $data['code'])->get()->first();

                if ($code) {
                    if ($code->STATUS == 1) {
                        $arr = array('message' => 'ขออภัยคะ โค้ดถูกใช้งานไปแล้ว!', 'state' => 'error');
                        return json_encode($arr);
                    }
                    if ($SL_STATUS == 'free') {
                        $arr = array('message' => 'ขออภัยคะ คุณติดสถานะฟรีเครดิต กรุณาเติมเงินเพื่อปลดล็อค!', 'state' => 'error');
                        return json_encode($arr);
                    }

                    $FREECREDIT_NORMAL = $code->AMOUNT;
                    $FREECREDIT_VIP = $code->AMOUNT;

                    if ($SL_STATUS == 'vip') {
                        $freecredit = $FREECREDIT_VIP;
                    } else if ($SL_STATUS == 'normal') {
                        $freecredit = $FREECREDIT_NORMAL;
                    } else {
                        $arr = array('message' => 'ขออภัย ลูกค้าไม่สามารถรับเครดิตฟรีซ้ำได้คะ', 'state' => 'error');
                        return json_encode($arr);
                    }

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 400); //timeout in seconds
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                    $form_field = array();
                    $form_field['username'] = $SLOT_USER;
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

                        if ($credit_before < 5) {

                            DB::table('credit_turnover')->where('CustomerID', $customerid)->where('TURN_STATUS', 0)->update(['TURN_STATUS' => 1]);

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                            $form_field = array();
                            $form_field['username'] = $SLOT_USER;
                            $form_field['amount'] = $freecredit;
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

                                DB::table('table_coupon')->where('ID', $code->ID)->update(['STATUS' => 1, 'DATE_TIME' => date('Y-m-d'), 'CustomerID' => $customerid]);

                                if ($SL_STATUS != 'free') {

                                    DB::table('account_users')->where('CustomerID', $customerid)->update(['SL_STATUS' => 'free']);
                                    $finsert = array(
                                        'CustomerID' => $customerid,
                                        'SL_STATUS' => 'free',
                                        'DATETIME' => date('Y-m-d H:i:s'),
                                    );
                                    DB::table('account_statuslog')->insert($finsert);
                                }
                                $arr = array('message' => 'ยินดีด้วยคะ ลูกค้ารับเครดิตฟรีสำเร็จแล้ว ยอดเงินคงเหลือคือ ' . $json->{'balance'} . ' บาท', 'state' => 'success');
                                return json_encode($arr);
                            } else {
                                $arr = array('message' => 'รับเครดิตฟรีไม่สำเร็จ กรุณาติดต่อแอดมินคะ', 'state' => 'error');
                                return json_encode($arr);
                            }
                        } else {
                            $arr = array('message' => 'ลูกค้าไม่สามารถรับเครดิตฟรีได้คะ เนื่องจากมีเครดิตมากกว่า 5 บาท', 'state' => 'error');
                            return json_encode($arr);
                        }
                    } else {
                        $arr = array('message' => 'ระบบผิดพลาดไม่สามารถเช็คเงินลูกค้าได้คะ กรุณาลองใหม่พายหลัง', 'state' => 'error');
                        return json_encode($arr);
                    }
                } else {
                    // เครดิตฟรีปกติ

                    if ($SL_FREECODE != $data['code']) {
                        $arr = array('message' => 'โค้ดเครดิตฟรีไม่ถูกต้อง', 'state' => 'error');
                        return json_encode($arr);
                    }

                    $flag = DB::table('credit_freelog')->where('CustomerID', $customerid)->get()->first();

                    if ($flag) {
                        $arr = array('message' => 'ลูกค้าเคยรับเครดิตฟรีแล้วคะ', 'state' => 'error');
                        return json_encode($arr);
                    }

                    $h = DB::table('codesms_setting')->where('ID', 1)->get()->first();

                    if ($h) {
                        if ($h->status == 0) {
                            $maxuser = $h->Max_user;
                        } else {
                            $maxuser = '0';
                        }
                    } else {
                        $maxuser = '0';
                    }

                    $j = DB::table('credit_freelog')->whereDate('FREE_DATETIME', date('Y-m-d'))->count();

                    if ($j >= $maxuser) {
                        $arr = array('message' => 'ขออภัย เครดิตฟรีเต็มแล้วคะ ลูกค้าสามารถรับได้อีกทีในวันถัดไปนะคะ ขอบคุณคะ', 'state' => 'error');
                        return json_encode($arr);
                    }

                    $k = DB::table('codesms_setting')->get()->first();

                    $FREECREDIT_NORMAL = $k->Max_credit;
                    $FREECREDIT_VIP = $k->Max_credit;

                    if ($SL_STATUS == 'vip') {
                        $freecredit = $FREECREDIT_VIP;
                    } else if ($SL_STATUS == 'normal') {
                        $freecredit = $FREECREDIT_NORMAL;
                    } else {
                        $arr = array('message' => 'ขออภัย ลูกค้าไม่สามารถรับเครดิตฟรีซ้ำได้คะ', 'state' => 'error');
                        return json_encode($arr);
                    }

                    $p = DB::table('credit_freelog')->whereDate('CustomerID', $customerid)->get()->first();

                    if (!$p) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 400); //timeout in seconds
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                        $form_field = array();
                        $form_field['username'] = $SLOT_USER;
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

                            if ($credit_before < 5) {

                                DB::table('credit_turnover')->where('CustomerID', $customerid)->where('TURN_STATUS', 0)->update(['TURN_STATUS' => 1]);

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                                curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                                $form_field = array();
                                $form_field['username'] = $SLOT_USER;
                                $form_field['amount'] = $freecredit;
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

                                    $tinsert = array(
                                        'CustomerID' => $customerid,
                                        'FREE_CODE' => $data['code'],
                                        'FREE_AMOUNT' => $freecredit,
                                        'FREE_DATETIME' => date('Y-m-d H:i:s'),
                                    );
                                    DB::table('credit_freelog')->insert($tinsert);

                                    if ($SL_STATUS != 'free') {

                                        DB::table('account_users')->where('CustomerID', $customerid)->update(['SL_STATUS' => 'free']);
                                        $finsert = array(
                                            'CustomerID' => $customerid,
                                            'SL_STATUS' => 'free',
                                            'DATETIME' => date('Y-m-d H:i:s'),
                                        );
                                        DB::table('account_statuslog')->insert($finsert);
                                    }
                                    $arr = array('message' => 'ยินดีด้วยคะ ลูกค้ารับเครดิตฟรีสำเร็จแล้ว ยอดเงินคงเหลือคือ ' . $json->{'balance'} . ' บาท', 'state' => 'success');
                                    return json_encode($arr);
                                } else {
                                    $arr = array('message' => 'รับเครดิตฟรีไม่สำเร็จ กรุณาติดต่อแอดมินคะ', 'state' => 'error');
                                    return json_encode($arr);
                                }
                            } else {
                                $arr = array('message' => 'ลูกค้าไม่สามารถรับเครดิตฟรีได้คะ เนื่องจากมีเครดิตมากกว่า 5 บาท', 'state' => 'error');
                                return json_encode($arr);
                            }
                        } else {
                            $arr = array('message' => 'ระบบผิดพลาดไม่สามารถเช็คเงินลูกค้าได้คะ กรุณาลองใหม่พายหลัง', 'state' => 'error');
                            return json_encode($arr);
                        }
                    } else {
                        $arr = array('message' => 'ลูกค้าไม่สามารถรับเครดิตฟรีได้คะ เนื่องจากเคยรับเครดิตฟรีไปแล้ว', 'state' => 'error');
                        return json_encode($arr);
                    }
                }
            }
        } else {
            $arr = array('message' => 'ไม่พบยูสเซอร์', 'state' => 'error');
            return json_encode($arr);
        }
    }

    public static function getBank()
    {

        $scb = DB::table('bank_setting')->where('bank_status', 0)->where('bank_type', 1)->get()->first();
        $bay = DB::table('bank_setting')->where('bank_status', 0)->where('bank_type', 6)->get()->first();
        $ktb = DB::table('bank_setting')->where('bank_status', 0)->where('bank_type', 4)->get()->first();
        $wallet = DB::table('bank_setting')->where('bank_status', 0)->where('bank_type', 22)->get()->first();

        if ($scb) {
            $bankscb_name = $scb->bank_name;
            $bankscb_number = $scb->bank_number;
            $bankscb_status = $scb->bank_status;
            $bankscb_type = $scb->bank_type;
        } else {
            $bankscb_name = null;
            $bankscb_number = null;
            $bankscb_status = null;
            $bankscb_type = null;
        }
        if ($bay) {
            $bankbay_name = $bay->bank_name;
            $bankbay_number = $bay->bank_number;
            $bankbay_status = $bay->bank_status;
            $bankbay_type = $bay->bank_type;
        } else {
            $bankbay_name = null;
            $bankbay_number = null;
            $bankbay_status = null;
            $bankbay_type = null;
        }
        if ($ktb) {
            $bankktb_name = $ktb->bank_name;
            $bankktb_number = $ktb->bank_number;
            $bankktb_status = $ktb->bank_status;
            $bankktb_type = $ktb->bank_type;
        } else {
            $bankktb_name = null;
            $bankktb_number = null;
            $bankktb_status = null;
            $bankktb_type = null;
        }
        if ($wallet) {
            $bankwallet_name = $wallet->bank_name;
            $bankwallet_number = $wallet->bank_number;
            $bankwallet_status = $wallet->bank_status;
            $bankwallet_type = $wallet->bank_type;
        } else {
            $bankwallet_name = null;
            $bankwallet_number = null;
            $bankwallet_status = null;
            $bankwallet_type = null;
        }
        $data = array(
            'bankscb_name' => $bankscb_name,
            'bankscb_number' => $bankscb_number,
            'bankscb_status' => $bankscb_status,
            'bankscb_type' => $bankscb_type,
            'bankbay_name' => $bankbay_name,
            'bankbay_number' => $bankbay_number,
            'bankbay_status' => $bankbay_status,
            'bankbay_type' => $bankbay_type,
            'bankktb_name' => $bankktb_name,
            'bankktb_number' => $bankktb_number,
            'bankktb_status' => $bankktb_status,
            'bankktb_type' => $bankktb_type,
            'bankwallet_name' => $bankwallet_name,
            'bankwallet_number' => $bankwallet_number,
            'bankwallet_status' => $bankwallet_status,
            'bankwallet_type' => $bankwallet_type,
            'state' => 'success',
        );
        return $data;
    }

    public static function getBankv2()
    {

        $bank = DB::table('bank_setting')->where('bank_status', 0)->orderBy('rating', 'asc')->get();

        $master = [];
        foreach ($bank as $res) {

            $infor = DB::table('bank_information')->where('BANK_ID', $res->bank_type)->get()->first();

            $namefi = explode(" ", $res->bank_name);
            array_push($master, array(
                'nameAccount' => $namefi[0],
                'account' => $res->bank_number,
                'cashActive' => $res->bank_status,
                'name_en' => strtolower($infor->BANK_CODE),
                'name_th' => $infor->BANK_NAME
            ));
        }


        $data = array(
            'data' => $master,
            'state' => 'success'
        );
        return $data;
    }

    public static function Alert()
    {

        $bank = DB::table('bank_setting')->where('bank_status', 0)->where('bank_type', 1)->get()->first();
        $infor = DB::table('bank_information')->where('BANK_ID', $bank->bank_type)->get()->first();

        $namefi = explode(" ", $bank->bank_name);

        $res['nameAccount'] = $namefi[0];
        $res['account'] = $bank->bank_number;
        $res['cashActive'] = $bank->bank_status;
        $res['name_en'] = strtolower($infor->BANK_CODE);
        $res['name_th'] = $infor->BANK_NAME;

        $data = array(
            'data' => $res,
            'state' => 'success'
        );
        return $data;
    }

    public static function chgPassword($data)
    {

        if (!preg_match('/^(?=.*\d)(?=.*[A-Za-z])[0-9A-Za-z]{8,24}$/', $data['new_pass'])) {
            return '5';
        }

        if ($data['old_pass'] == $data['new_pass']) {
            return '6';
        }

        $io = DB::table('account_session')->where('SL_SESSION', $data['token'])->where('SL_LOGINIP', $data['ip'])->get()->first();

        if ($io) {
            $customerid = $io->CustomerID;
            echo $data['old_pass'];
            $flag1 = DB::table('account_users')->where('SL_PASSWORD', md5($data['old_pass']))->where('SLOT_USER', $data['username'])->where('CustomerID', $customerid)->get()->first();

            if ($flag1) {

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300); //timeout in seconds
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $form_field = array();
                $form_field['username'] = $data['username'];
                $form_field['password'] = $data['new_pass'];
                $post_string = '';

                foreach ($form_field as $key => $value) {
                    $post_string .= $key . '=' . urlencode($value) . '&';
                }

                $post_string = substr($post_string, 0, -1);

                curl_setopt($ch, CURLOPT_URL, 'https://mftx.slotxo-api.com/?agent=' . config('app.AG_AGENT') . '&method=sp');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);

                $response = curl_exec($ch);
                curl_close($ch);
                $json = json_decode($response);

                if ($json->{'result'} == 'ok') {

                    // insert logs
                    $oinsert = array(
                        'CustomerID' => $customerid,
                        'SLOT_USER' => $data['username'],
                        'OLD_PASS' => $data['old_pass'],
                        'NEW_PASS' => $data['new_pass']
                    );

                    DB::table('logs_chgpassword')->insert($oinsert);

                    DB::table('account_users')->where('CustomerID', $customerid)->update(['SL_PASSWORD' => md5($data['new_pass'])]);

                    DB::table('account_session')->where('CustomerID', $customerid)->update(['SL_SESSION' => '0', 'SL_ADMIN_SESSION' => null, 'SL_LOGINIP' => '0']);
                    return '1';
                } else {
                    return '4';
                }
            } else {
                return '2';
            }
        } else {
            return '3';
        }
    }

    public static function getPromotion()
    {

        $bank = DB::table('bonus_settings')->where('SL_STATUS', 1)->orderBy('ID', 'asc')->get();

        $master = [];
        foreach ($bank as $res) {

            array_push($master, array(
                'bonus_title' => $res->SL_TITLE,
                'bonus_content' => $res->SL_CONTENT,
                'bonus_image' => $res->SL_IMAGE,
                'bonus_func' => $res->SL_FUNC
            ));
        }


        $data = array(
            'data' => $master,
            'state' => 'success'
        );
        return json_encode($data);
    }

    // ฝากเงิน v2
    public static function getDepositsv2($res)
    {
        $users = DB::table('logs_depositlog')->where('CustomerID', $res['id'])->where('DEPOSIT_STATUS', 2)->orderByDesc('DEPOSIT_DATETIME')->paginate(10);
        return json_encode($users);
    }

    // ย้ายเงิน v2
    public static function getTransfersv2($res)
    {
        $users = DB::table('logs_transferlog')->where('CustomerID', $res['id'])->orderByDesc('WITHDRAW_DATETIME')->paginate(10);
        return json_encode($users);
    }

    // ถอนเงิน v2
    public static function getWithdrawsv2($res)
    {
        $users = DB::table('credit_withdrawlog')->where('CustomerID', $res['id'])->orderByDesc('WITHDRAW_DATETIME')->paginate(10);
        return json_encode($users);
    }

    // ฝากเงิน
    public static function getDeposits($res)
    {
        $data = DB::table('logs_depositlog')
            ->join('account_users', 'logs_depositlog.CustomerID', '=', 'account_users.CustomerID')
            ->where('logs_depositlog.CustomerID', $res['id'])
            ->select('logs_depositlog.CustomerID', 'logs_depositlog.ID', 'account_users.CustomerID', 'logs_depositlog.DEPOSIT_TYPE', 'logs_depositlog.DEPOSIT_AMOUNT', 'logs_depositlog.DEPOSIT_DATETIME')
            ->orderBy('logs_depositlog.CustomerID', 'desc')
            ->limit(10)
            ->get();
        return $data;
    }

    // ย้ายเงิน
    public static function getTransfers($res)
    {
        $data = DB::table('logs_transferlog')
            ->join('account_users', 'logs_transferlog.CustomerID', '=', 'account_users.CustomerID')
            ->where('logs_transferlog.CustomerID', $res['id'])
            ->select('logs_transferlog.CustomerID', 'logs_transferlog.WITHDRAW_TXID', 'account_users.CustomerID', 'logs_transferlog.WITHDRAW_STATUS', 'logs_transferlog.WITHDRAW_AMOUNT', 'logs_transferlog.WITHDRAW_DATETIME', 'logs_transferlog.TURNOVER_ID')
            ->orderBy('logs_transferlog.CustomerID', 'desc')
            ->limit(10)
            ->get();
        return $data;
    }

    // ถอนเงิน
    public static function getWithdraws($res)
    {
        $transactions = DB::table('credit_withdrawlog')
            ->join('account_users', 'credit_withdrawlog.CustomerID', '=', 'account_users.CustomerID')
            ->where('credit_withdrawlog.CustomerID', $res['id'])
            ->select('credit_withdrawlog.CustomerID', 'credit_withdrawlog.WITHDRAW_TXID', 'account_users.CustomerID', 'credit_withdrawlog.WITHDRAW_STATUS', 'credit_withdrawlog.WITHDRAW_AMOUNT', 'credit_withdrawlog.WITHDRAW_DATETIME', 'credit_withdrawlog.WITHDRAW_TEXT')
            ->orderBy('credit_withdrawlog.CustomerID', 'desc')
            ->limit(10)
            ->get();
        return $transactions;
    }

    public static function freecode($length)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    public static function freecode1($length)
    {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    public static function freecode2($length)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function randomuser($length)
    {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
