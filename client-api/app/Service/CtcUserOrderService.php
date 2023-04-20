<?php


namespace App\Services;

use App\Model\CtcMerchantOrder;
use App\Model\CtcPaymentType;
use App\Model\CtcUserOrder;
use App\Model\CtcUserPayment;
use App\Model\FinanceLog;
use App\Model\User;
use App\Model\UserAssetRecharges;
use Illuminate\Support\Facades\DB;

class CtcUserOrderService extends BaseService
{
    // 取消次数
    public static function getMissionsCount($userid, $start_time)
    {
        return CtcUserOrder::query()->where('userid', $userid)
            ->where('created_time', '>', $start_time)
            ->where('status', 'cancel')
            ->count() ?? 0;
    }

    //
    public static function getUserSumAmount($userid, $type, $symbol, $status = '')
    {
        return CtcUserOrder::query()->where('userid', $userid)
            ->where('created_time', '>', strtotime(date("Y-m-d")))
            ->when($status, function($q) use($status){
                return $q->where('status', $status);
            })
            ->where('symbol', $symbol)
            ->where('type', $type)
            ->select([
                DB::raw('sum(quantity) sum_num'),
                DB::raw('count(id) sum_count'),
                DB::raw('sum(amount) sum_amount'),
            ])->first();
    }

    public static function checkBuySell($request, $type)
    {
        // 1、判断累计取消次数
        // 获取配置
        $conf = CtcConfService::getConf();
        switch ($conf->missions_cancelled_type){
            case "today":
                $start_time = strtotime(date("Y-m-d"));
                break;
            case "4h":
                $start_time = time()-4*60*60;
                break;
            case "24h":
                $start_time = time() - 86400;
                break;
            default:
                $start_time = strtotime(date("Y-m-d"));
                break;
        }
        $user = $request->user;
        $userid = $user['user_id'];
        // 获取取消次数
        $count = self::getMissionsCount($userid, $start_time);
        $checkError = '由于今日多次取消交易，已暂停今日法币交易权限!';
        if ($count >= $conf->missions_cancelled_max)
        {
            static::addError($checkError,400);
            return false;
        }
        // 2、判断实名
        $code = 302;
        $checkError = '您的法币交易信息暂未完善，请先完成';
        if (!UserService::checkUserData($userid, 'is_realname')){
            static::addError($checkError.'实名设置！',$code);
            return false;
        }
        // 3、判断资金密码
        if (!UserService::checkUserData($userid, 'paypassword')){
            static::addError($checkError.'-资金密码设置！',$code);
            return false;        }
        // 6、判断收款方式
        $resPay = CtcUserPaymentService::getUserPayment($userid);
        if (!$resPay)
        {
            static::addError($checkError.'-收款方式设置！',$code);
            return false;
        }
        $code = 400;
        // 4、价格判断
        DB::beginTransaction();
        if ($type == 'buy'){
            $merchant_type = 'sell';
        }else
            $merchant_type = 'buy';

        $data = CtcMerchantOrder::query()->lock()
            ->where('status', 'normal')
            ->where('type', $merchant_type)
            ->with('merchantPayment', 'merchant')
            ->lock()
            ->find($request->input('id'));
        if (!$data)
        {
            static::addError('价格已被更新，请重新选择',$code);
            return false;
        }

        // 1.1 sell 、判断余额
        $symbol = $data->symbol;
        $quantity = $request->input('quantity');
        $text_type = "起购";
        if ($type == 'sell')
        {
            $text_type = "出售";
            $balance = UserAssetRecharges::query()->lockForUpdate()
                ->where('userid', $userid)
                ->where('coin', $symbol)
                ->first();

            if (!$balance ||  bccomp($balance->quantity, $quantity, 2) === -1)
            {
                DB::rollback();
                static::addError('资金账户余额不足',$code);
                return false;
            }
        }

        $price = $request->input('price');
        if ( bccomp($data->price,  $price, 2)!=0)
        {
            DB::rollback();
            static::addError('价格已被更新，请重新选择',$code);
            return false;
        }

        // 5、判断起购数量
        if ($data->min_quantity_symbol && bccomp($quantity, $data->min_quantity_symbol, 2) === -1)
        {
            DB::rollback();
            static::addError( "单笔{$text_type}数量为{$data->min_quantity_symbol}".strtoupper($data->symbol),$code);
            return false;
        }

        //6、判断最大额
        $amount = bcmul($quantity, $price, 2);
        if ($data->max_quantity_cny && bccomp($amount, $data->max_quantity_cny, 2) === 1)
        {
            DB::rollback();
            static::addError(  "单笔最大额为{$data->max_quantity_cny}CNY",$code);
            return false;
        }

        // 7、判断使用最大额度
        $userSum = CtcUserOrderService::getUserSumAmount($userid, $type, $data->symbol);
        if ($type == 'buy')
        {
            if (bccomp($userSum->sum_count, $conf->max_buy_count,  2) === 1){
                {
                    DB::rollback();
                    static::addError(  '超过今日最大购买次数', $code);
                    return false;
                }
            }

            $userSum = CtcUserOrderService::getUserSumAmount($userid, $type, $data->symbol, 'finish');
            if (bccomp($userSum->sum_amount, $conf->max_buy_amount,  2) === 1){
                {
                    DB::rollback();
                    static::addError(  '今日最大买入额度：'.number_format($conf->max_buy_amount, 2, '.', '')."CNY", $code);
                    return false;
                }
            }
        }

        if ($type == 'sell')
        {
            if (bccomp($userSum->sum_count, $conf->max_sell_count,  2) === 1){
                {
                    DB::rollback();
                    static::addError(  '超过今日最大出售次数', $code);
                    return false;
                }
            }

            $userSum = CtcUserOrderService::getUserSumAmount($userid, $type, $data->symbol, 'finish');
            if (bccomp($userSum->sum_amount, $conf->max_sell_amount,  2) === 1){
                {
                    DB::rollback();
                    static::addError(  '今日最大出售额度：'.number_format($conf->max_sell_amount, 2, '.', '')."CNY", $code);
                    return false;
                }
            }

            $r = self::checkSell($userid, $data, $quantity, $balance);
            if (!$r)
            {
                DB::rollback();
                static::addError('下单失败',$code);
                return false;
            }
        }
        $res = self::createdOrder($userid, $data, $quantity, $price, $resPay, $conf,$type);
        if (!$res)
        {
            DB::rollback();
            static::addError(  '下单失败',$code);
            return false;
        }
        DB::commit();
        return $res;
    }

    public static function createdOrder($userid, $merchantOrder, $quantity, $price, $payData, $ctc_conf, $type = 'buy')
    {
        // TODO 冻结商家余额
        $payType = CtcPaymentType::query()->find($payData->payment_type_id);
        $user_pay_info = [
            'id' => $payData->id,
            'type' => $payType->type,
            'text_type' => $payType->name,
            'number' => $payData->number,
            'payee' => $payData->payee,
            'bank' => $payData->bank,
            'sub_bank' => $payData->sub_bank
        ];

        $merchant_pay_info = [];
        if ($type == 'buy')
        {
            $merchantPay = $merchantOrder->merchantPayment;
            $t_id = $merchantPay->payment_type_id;
            $payType = CtcPaymentType::query()->find($t_id);
            $merchant_pay_info = [
                'id' => $merchantOrder->merchantPayment->id,
                'type' => $payType->type,
                'text_type' => $payType->name,
                'name' => $merchantOrder->merchant->name,
                'number' => $merchantOrder->merchantPayment->number,
                'payee' => $merchantOrder->merchantPayment->payee,
                'bank' => $merchantOrder->merchantPayment->bank,
                'sub_bank' => $merchantOrder->merchantPayment->sub_bank
            ];
        }else{
            $merchantPay = $merchantOrder->merchantPayment;
            if ($merchantPay){
                $t_id = $merchantPay->payment_type_id;
                $payType = CtcPaymentType::query()->find($t_id);
                $merchant_pay_info = [
                    'id' => $merchantOrder->merchantPayment->id,
                    'type' => $payType->type,
                    'text_type' => $payType->name,
                    'name' => $merchantOrder->merchant->name,
                    'number' => $merchantOrder->merchantPayment->number,
                    'payee' => $merchantOrder->merchantPayment->payee,
                    'bank' => $merchantOrder->merchantPayment->bank,
                    'sub_bank' => $merchantOrder->merchantPayment->sub_bank
                ];
            }
            $merchant_pay_info['name'] = $merchantOrder->merchant->name;
        }

        $insert = [
            'userid' => $userid,
            'merchant_id' => $merchantOrder->merchant_id,
            'merchant_order_id' => $merchantOrder->id,
            'symbol' => $merchantOrder->symbol,
            'price' => $price,
            'type' => $type,
            'status ' => 'wait',
            'quantity' => $quantity,
            'amount' => bcmul($quantity, $price, 2),
            'user_pay_info' => $user_pay_info,
            'merchant_pay_info' => $merchant_pay_info,
            'pay_wait_time' => $ctc_conf->pay_wait_time,
            'expiration_time' => $ctc_conf->pay_wait_time * 60 + time(),
            'remark' => rand(100000,999999),
        ];
        $res = CtcUserOrder::query()->create($insert);
        $res->order_no = self::generateOrderNumber($res->id);
        if(!$res->save()){
            return false;
        }
        return $res->order_no;

    }

    public static function checkSell($userid, $merchantOrder, $quantity, $fromAsset)
    {
        // 流程
        // 1、扣钱 、冻结加钱
        $symbol = $merchantOrder->symbol;
        $update_data = [
            'freeze' => DB::raw("freeze + $quantity"),
            'quantity' => DB::raw("quantity - $quantity")
        ];
        $res = UserAssetRecharges::query()
            ->where('userid', $userid)
            ->where('coin', $symbol)
            ->update($update_data);
        if (!$res){
            return false;
        }
        FinanceLog::insert(array(
            'userid' => $userid,
            'coin' => $symbol,
            'old_quantity' => $fromAsset->quantity,
            'old_freeze' => $fromAsset->freeze,
            'new_quantity' => bcsub($fromAsset->quantity, $quantity, 8),
            'new_freeze' => bcadd($fromAsset->freeze, $quantity, 8),
            'quantity' => 0-$quantity,
            'freeze' => $quantity,
            'behavior' => 'c2c_sell',
            'behavior_id' => 0,
            'created_at' => time(),
            'account' => 'asset',
            'status' => 1
        ));
        return true;
    }


    public static function change($order_no, $userid, $status, $type)
    {
        switch ($status)
        {
            case "cancel":
                $condition['status'] = 'wait';
                break;
            case 'paid':
                $condition['status'] = 'wait';
                break;
            default:
                $condition = [];
                break;
        }
        $data = CtcUserOrder::query()
            ->where('userid', $userid)
            ->where('type', $type)
            ->where('order_no', $order_no)
            ->when($condition, function ($q) use ($condition){
                return $q->where($condition);
            })
            ->lockForUpdate()
            ->first();
        if (!$data)
            return ['status' => false, 'msg' => '订单不存在'];

        $data->status = $status;
        if ($status == 'paid')
            $data->pay_time = date("Y-m-d H:i:s");
        $data->save();
        return ['status' => true];
    }

    public static function details($userid, $order_no, $status = '')
    {
        $data = CtcUserOrder::query()
            ->where('userid', $userid)
            ->when($status, function ($q) use ($status){
                return $q->where('status', $status);
            })
            ->where('order_no', $order_no)
            ->first();
        return $data;
    }

    public static function generateOrderNumber(int $id): string
    {
        return mt_rand(10, 99)
            . sprintf('%010d', time() - 946656000)
            . sprintf('%03d', (float)microtime() * 1000)
            . sprintf('%03d', (int)$id % 1000);
    }

    // 验证
    public static function checkUserCtcInfo($userid)
    {
        $user = User::query()->find($userid);
        $data = [
            'is_realname' => $user->is_realname,
            'text_realname' => $user->is_realname ? '已认证':'未认证',
            'pay_pwd' => $user->paypassword ? 1:0,
            'text_pay_pwd' => $user->paypassword ? '已设置':'未设置',
        ];
        $pay = CtcUserPayment::query()->where('userid', $userid)->where('status', 1)->count();
        $data['isset_payment'] = $pay>0 ? 1:0;
        $data['text_isset_payment'] = $pay>0 ? '已绑定':'未绑定';
        return $data;
    }

}