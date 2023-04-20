<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * Class RedisCode
 * redis 枚举类
 * @Constants
 * @package App\Constants
 * @Author hy
 */
class RedisCode extends AbstractConstants
{
    /**
     * nft爬虫队列标识
     */
    const REPTILE_TASK = "reptask:nft_reptile";


    /**
     * 后台数据缓存总目录
     */
    const HOME = "admin";






}
