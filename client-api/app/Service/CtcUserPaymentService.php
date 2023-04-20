<?php


namespace App\Services;

use App\Model\CtcUserPayment;

class CtcUserPaymentService extends BaseService
{
    public static function getUserPayment($userid)
    {
        $data = CtcUserPayment::query()
            ->where('userid', $userid)
            ->where('status', 1)
            ->where('is_delete', 0)
            ->first();
        if (!$data)
            return false;
        return $data;
    }


    public static function created($userid, $bank, $realname, $sub_bank, $number, $type_id = 1)
    {
        $count = CtcUserPayment::query()->where('userid', $userid)->where('is_delete', 0)->count();
        $status = 0;
        if ($count == 0){
            $status = 1;
        }
        $payment = new CtcUserPayment();
        $payment->userid = $userid;
        $payment->payee = $realname;
        $payment->bank = $bank;
        $payment->sub_bank = $sub_bank;
        $payment->number = $number;
        $payment->status = $status;
        $payment->payment_type_id = $type_id;
        return $payment->save();
    }
}