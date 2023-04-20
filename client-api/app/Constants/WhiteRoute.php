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
 * Class WhiteRoute
 * 上传相关错误码
 * @Constants
 * @package App\Constants
 * @Author tian
 * @Date: 2022/4/14
 */
class WhiteRoute extends AbstractConstants
{

    /**
     * @Message("免token路由白名单设置")
     */
    const ROUTE_LIST = [
        '/common/sys_config', 
        '/common/auth/verification_code', 
        '/auth/login', 
        '/auth/register', 
        '/test', 
        '/wekjwe'
    ];

}

