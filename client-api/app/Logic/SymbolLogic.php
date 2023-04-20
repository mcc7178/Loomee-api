<?php


namespace App\Logic;


use App\Model\CoinChain;
use App\Model\Coins;
use App\Model\RechargeAddress;
use App\Model\UserAssetRecharges;
use App\Model\UserRechargeAddressReserved;
use Illuminate\Support\Facades\DB;

class SymbolLogic
{
    public static function getSymbolDepositAndWithdrawalList()
    {
        $data = CoinChain::query()
            ->select([
                'symbol',
                DB::raw("GROUP_CONCAT(is_recharge) as recharge_status"),
                DB::raw("GROUP_CONCAT(is_out) as is_withdraw"),
            ])
            ->groupBy('symbol')
            ->get();

        foreach ($data as $k => $v) {
            $v->is_withdraw_text = trans('symbol.Withdrawal suspension');
            $v->is_recharge_text = trans('symbol.Suspend recharge');
            $v->symbol = strtoupper($v->symbol);
            $v->is_recharge = 0;

            if (strpos($v->recharge_status, CoinChain::RECHARGE_Y) !== false) {
                $v->is_recharge = 1;
                $v->is_recharge_text = '';
            }

            if (strpos($v->is_withdraw, CoinChain::WITHDRAW_Y) !== false) {
                $v->is_withdraw = 1;
                $v->is_withdraw_text = '';
            }

        }

        return $data;
    }

    public static function getSymbolChain($symbol, $userID = false)
    {
        $symbol = strtolower(trim($symbol));

        $data = Coins::query()
            ->with(['chain' => function($query){
                return $query->where('is_recharge', 1);
            }])
            ->where('symbol', $symbol)
            ->get();

        $userAddress = RechargeAddress::query()
            ->where('userid', $userID)
            ->where('coin', $symbol)
            ->get()->keyBy('chain_id');

        foreach ($data as $key => $val) {

            if (!empty($val->chain))
            {
                foreach ($val->chain as $k => $chainCoin)
                {
                    $chainCoin->recharge_notice = CoinChain::query()
                    ->where('symbol', $symbol)
                    ->where('chain_id', $chainCoin->id)
                    ->value(self::getLangField('recharge'));

                    $chainCoin->is_address = isset($userAddress[$chainCoin->id]) ? true : false;
                    $chainCoin->address = isset($userAddress[$chainCoin->id]) ? $userAddress[$chainCoin->id]->address : '';

                    if(empty( $chainCoin->address)){
                        $row = UserRechargeAddressReserved::query()->where(['chain_id'=>$chainCoin->id,'status'=> 0])->first();
                        if(!empty($row)){
                            $row->status = 1;
                            $row->save();
                            $address = $row->address;
                            $chainCoin->address = $row->address;
                            RechargeAddress::query()->create([
                                'userid'   => $userID,
                                'coin'      => $symbol,
                                'address'   => $address,
                                'status'    => 1,
                                'chain_id'  => $chainCoin->id
                            ]);
                        }
                    }

                }
            }
        }

        return $data;
    }

    public static function getSymbolWithdrawChain($symbol, $userID = false)
    {
        $symbol = strtolower(trim($symbol));

        $data = Coins::query()
            ->with(['chain' => function($query){
                return $query->where('is_out', 1);
            }])
            ->where('symbol', $symbol)
            ->get();
        $data[0]->asset = UserAssetRecharges::query()->where('userid',$userID)->where('coin', $symbol)->first();

        foreach ($data as $key => $val) {

            if (!empty($val->chain))
            {
                foreach ($val->chain as $k => $chainCoin)
                {
                    $conf = CoinChain::query()
                        ->where('symbol', $symbol)
                        ->where('chain_id', $chainCoin->id)
                        ->first();

                    $chainCoin->withdraw_notice = $conf->{self::getLangField('withdraw')};
                    $chainCoin->withdraw_min = $conf->out_min;
                    $chainCoin->withdraw_fee_limit = $conf->out_fee_limit;
                    $chainCoin->withdraw_fee_ratio = $conf->out_fee_ratio;
                }
            }
        }

        return $data;
    }

    public static function getLangField($behavior)
    {
        if ($behavior == 'withdraw'){
            if( app('translator')->getLocale() == CoinChain::EN )
                return CoinChain::EN_WITHDRAW_NOTICE;
            return CoinChain::ZH_WITHDRAW_NOTICE;
        }
        if ($behavior == 'recharge'){
            if( app('translator')->getLocale() == CoinChain::EN )
                return CoinChain::EN_RECHARGE_NOTICE;
            return CoinChain::EN_RECHARGE_NOTICE;
        }
    }

}
