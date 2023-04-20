<?php

namespace App\Task;

use App\Services\KlineService;
use App\Utils\Redis;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;

class KlineWeekMonthTask
{
    protected $signature = 'kline-weekMonth';

    protected $description = '计算K线周-月';

    /**
     * @Inject()
     * @var StdoutLoggerInterface
     */
    private $logger;


    public function handle()
    {
        $this->mainMonth();
    }

    public function main()
    {
        $redis = Redis::getInstance();

        $kline_key = 'market_kline_data:fil/usdt-1day';
        $data = $redis->hGetAll($kline_key);

        // 获取每个周的时间
        $startData = strtotime('2021-4-5');
        $endData = time();
        $kLineData = $cacheData = [];


        for ($i = 1; $i < 100; $i++) {
            $weekEndData = $startData + (7 * 60 * 24 * 60);
            if ($startData >= $endData) {
                break;
            }
            // 1641830400
            foreach ($data as $kTime => $kLine) {
                if ($kTime >= $startData && $kTime < $weekEndData) {
                    $kLine = json_decode($kLine, true);
                    $kLine['time'] = $kTime;
                    $kLineData[$startData][] = $kLine;
                }
            }
            var_dump(date("Y-m-d H:i:s", $startData) . ' ======= ' . date("Y-m-d H:i:s", $weekEndData));
            $startData = $weekEndData;
        }
        if (!empty($kLineData)) {
            var_dump(json_encode($kLineData));
            foreach ($kLineData as $time => $v) {
                $v = (new KlineService())->arraySort($v, 'time', SORT_ASC);
                $open = $high = $low = $end = 0;
                foreach ($v as $k => $item) {
                    if ($k == 0)
                        $open = $item['open'];
                    $high = $item['high'];
                    if ($low > 0)
                        $low = $item['low'] < $low ? $item['low'] : $low;
                    else
                        $low = $item['low'];
                    if ($high > 0)
                        $high = $item['high'] < $low ? $item['high'] : $high;
                    else
                        $high = $item['high'];
                    $close = $item['close'];
                }
                $xx = $cacheData[$time] = [
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                    'close' => $close
                ];

                var_dump($kline_key);
                $kline_key = 'market_kline_data:fil/usdt-1week';
                $r = $redis->hSet($kline_key, $time, json_encode($xx));
                var_dump($r);
            }
        }
        var_dump(json_encode($cacheData));
    }

    public function mainMonth()
    {
        $kline_key = 'market_kline_data:fil/usdt-1day';
        $redis = Redis::getInstance();
        $redisData = $redis->hGetAll($kline_key);

        // 获取每个周的时间
        $kLineData = $cacheData = [];
        $date = [];
        $startYear = 2021;
        $endYear = (int)date('Y');
        $startMonth = 4;
        $endMonth = (int)date('m');
        for ($y = $startYear; $y <= $endYear; $y++) {
            for ($m = $startMonth; $m <= $endMonth; $m++) {
                $month = $m < 10 ? '0' . $m : $m;
                $date[] = "$y-$month";
            }
        }

        foreach ($date as $time) {
            foreach ($redisData as $kTime => $kLine) {
                if (date("Y-m", $kTime) == $time) {
                    $kLine = json_decode($kLine, true);
                    $kLine['time'] = $kTime;
                    $kLineData[strtotime($time)][] = $kLine;
                }
            }
        }

        if (!empty($kLineData)) {
            foreach ($kLineData as $time => $v) {
                $v = (new KlineService())->arraySort($v, 'time', SORT_ASC);
                $open = $high = $low = 0;
                foreach ($v as $k => $item) {
                    if ($k == 0)
                        $open = $item['open'];
                    $high = $item['high'];
                    if ($low > 0)
                        $low = min($item['low'], $low);
                    else
                        $low = $item['low'];
                    if ($high > 0)
                        $high = $item['high'] < $low ? $item['high'] : $high;
                    else
                        $high = $item['high'];
                    $close = $item['close'];
                }
                $xx = $cacheData[$time] = [
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                    'close' => $close
                ];

                $kline_key = 'market_kline_data:fil/usdt-1month';
                $r = $redis->hSet($kline_key, $time, json_encode($xx));
            }
        }
    }
}