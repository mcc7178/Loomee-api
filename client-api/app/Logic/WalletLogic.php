<?php


namespace App\Logic;


use App\Exceptions\BaseException;
use App\Model\FinanceLog;
use App\Model\TransferLog;
use App\Model\UserAssetRecharges;
use App\Services\CommonService;
use Hyperf\DbConnection\Db;
use App\Exception\DbException;

class WalletLogic
{

    const STATUS = [
        0 => '失败',
        1 => '已完成',
    ];
    const EN = 'en';
    const EN_STATUS = [
        0 => 'Fail',
        1 => 'Success',
    ];
    const EN_BEHAVIOR = [
        'transfer' => 'Transfer Accounts',
        'exchange' => 'Flash Cash'
    ];

    const BEHAVIOR = [
        'transfer' => '转账',
        'exchange' => '闪兑'
    ];

    public static $finance_status = 1;

    public static function userAssets($data)
    {
        foreach ($data as &$value) {
            $value['quantity'] = !empty($value['user_asset_recharges']) ? bcadd($value['user_asset_recharges']['quantity'], 0, 8) : 0;
            $value['freeze'] = !empty($value['user_asset_recharges']) ? bcadd($value['user_asset_recharges']['freeze'], 0, 8) : 0;
            unset($value['user_asset_recharges']);
            unset($value['decimals']);
        }
        return $data;
    }

    public static function financeLog($coins, $finance_log)
    {
        $_finance_log = [];
        foreach ($finance_log as $value) {
            $_finance_log[] = [
                //todo 环境变量配置
                //'behavior' => app('translator')->getLocale() == self::EN ? self::EN_BEHAVIOR[$value->behavior] : self::BEHAVIOR[$value->behavior],
                'behavior' => self::BEHAVIOR[$value->behavior],
                'quantity' => $value->quantity,
                //todo 环境变量配置
//                'status' =>  app('translator')->getLocale() == self::EN ? self::EN_STATUS[$value->status] : self::STATUS[$value->status],
                'status' => self::STATUS[$value->status],
                'created_at' => $value->created_at
            ];
        }
        return [
            'coins' => [
                'id' => $coins->id,
                'symbol' => $coins->symbol,
                'quantity' => !empty($coins->user_asset_recharges) ? $coins->user_asset_recharges->quantity : 0,
                'freeze' => !empty($coins->user_asset_recharges) ? $coins->user_asset_recharges->freeze : 0,
            ],
            'finance_log' => $_finance_log
        ];
    }

    public static function buildLogDetails($data)
    {
        $to_user = !empty($data['to_user']) ? $data['to_user']['username'] : '';
//        $data['status'] = self::STATUS[$data['status']];
//        $data['status'] = app('translator')->getLocale() == self::EN ? self::EN_STATUS[$data['status']] : self::STATUS[$data['status']];
        //todo 环境变量配置
        $data['status'] = self::STATUS[$data['status']];
        unset($data['to_user']);
        $data['to_user'] = $to_user;
        unset($data['userid'], $data['to_user_id'], $data['symbol'], $data['fee_quantity'], $data['fee_symbol'], $data['created_ip']);
        return $data;
    }

    public static function buildLogs($data)
    {
        if (!empty($data['data'])) {
            foreach ($data['data'] as &$value) {
                $value['to_user'] = !empty($value['to_user']) ? $value['to_user']['username'] : '';
//                $value['status'] = self::STATUS[$value['status']];
                //todo 环境变量配置
//                $value['status'] = app('translator')->getLocale() == self::EN ? self::EN_STATUS[$value['status']] : self::STATUS[$value['status']];
                $value['status'] = self::STATUS[$value['status']];
                unset($value['userid'], $value['to_user_id'], $value['fee_quantity'], $value['fee_symbol'], $value['created_ip']);
            }
        }
        return $data;
    }

    /**
     * 获取单币种余额
     * @param $user_id
     * @param $symbol
     */
    public static function getUserSymbolAsset($user_id, $symbol)
    {
        $symbol = strtolower($symbol);
        return UserAssetRecharges::query()
            ->firstOrCreate([
                'userid' => $user_id,
                'coin' => $symbol
            ], [
                'quantity' => 0,
                'freeze' => 0,
            ]);
    }

    /**
     * 转账操作
     * @param $user_id
     * @param $symbol
     * @param $action
     * @param $quantity
     * @param int $freeze
     * @param int $action_id
     * @param string $remark
     * @return mixed
     * @throws BaseException
     */
    public static function saveUserSymbolAsset($user_id, $symbol, $action, $quantity, $freeze = 0, $action_id = 0, $remark = '')
    {
        $symbol = strtolower($symbol);
        $search = [
            'userid' => $user_id,
            'coin' => $symbol
        ];
        $result = UserAssetRecharges::query()
            ->where($search)
            ->lockForUpdate()
            ->first();

        if (!$result) {
            if ($freeze < 0)
                throw new DbException('wallet.Insufficient balance');
            $insert_data = [
                'userid' => $user_id,
                'coin' => $symbol,
                'quantity' => $quantity,
                'freeze' => $freeze
            ];
            $result = UserAssetRecharges::query()->create($insert_data);
        } else {

            $update_data = [
                'freeze' => DB::raw("freeze + $freeze"),
                'quantity' => DB::raw("quantity + $quantity")
            ];
            UserAssetRecharges::query()->where($search)->update($update_data);
        }
        return self::createdFinanceLog($user_id, $result, $quantity, $action, $action_id, $freeze, $remark);
    }

    /**
     * 创建转账流水日志
     * @param $userid
     * @param $result
     * @param $quantity
     * @param $action
     * @param int $action_id
     * @param int $freeze
     * @param string $remark
     */
    public static function createdFinanceLog($userid, $result, $quantity, $action, $action_id = 0, $freeze = 0, $remark = '')
    {
        bcscale(8);
        return FinanceLog::query()
            ->create([
                'userid' => $userid,
                'coin' => $result->coin,
                'old_quantity' => $result->quantity,
                'old_freeze' => $result->freeze,
                'new_quantity' => bcadd($result->quantity, $quantity),
                'new_freeze' => bcadd($result->freeze, $freeze),
                'quantity' => $quantity,
                'freeze' => $freeze,
                'status' => self::$finance_status,
                'behavior' => $action,
                'behavior_id' => $action_id,
                'remark' => $remark,
                'created_at' => time()
            ]);
    }

    /**
     * 创建转账日志
     * @param $userid
     * @param $to_user_id
     * @param $number
     * @param $coin
     * @param $transfer_fee_quantity
     */
    public static function createdTransferLog($userid, $to_user_id, $number, $coin, $transfer_fee_quantity)
    {
        bcscale(8);
        $created = TransferLog::query()
            ->create([
                'userid' => $userid,
                'to_user_id' => $to_user_id,
                'quantity' => $number,
                'symbol' => $coin,
                'fee_quantity' => $transfer_fee_quantity,
                'fee_symbol' => $coin,
                'status' => 1,
                'created_at' => time(),
                'created_ip' => CommonService::get_client_ip(),
            ]);
        return $created->id;
    }

    /**
     * 我的资产(转成USDT)
     * @param $user_id
     */
    public static function myAssets($user_id)
    {
        $userAssetRecharges = UserAssetRecharges::query()->where('userid', $user_id)->get();
        foreach ($userAssetRecharges as &$item) {
            $marketPrice = MarketLogic::getMarketPrice($item->coin);
            if ($item->coin !== 'usdt') {
                $item->quantity = bcmul($item->quantity, $marketPrice, 8);
                $item->freeze = bcmul($item->freeze, $marketPrice, 8);
            }
        }
        return $userAssetRecharges;
    }
}
