<?php

namespace App\Service\Auth;

use App\Constants\StatusCode;
use App\Foundation\Traits\Singleton;
use App\Model\Auth\Member;
use App\Model\Product\Product;
use App\Service\BaseService;
use App\Service\System\LoginLogService;
use App\Model\Auth\Permission;
use App\Model\Auth\User;
use App\Model\System\LoginLog;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\ApplicationContext;
use Phper666\JWTAuth\JWT;
use App\Utils\Cache;


/**
 * 登陆服务基础类
 * Class LoginService
 * @package App\Service\Auth
 * @Author YiYuan-Lin
 * @Date: 2020/10/29
 */
class LoginService extends BaseService
{
    use Singleton;

    /**
     * @Inject()
     * @var JWT
     */
    private $jwt;
    /**
     * @Inject()
     * @var \Hyperf\Contract\ConfigInterface
     */
    private $config;

    /**
     * 处理登陆逻辑
     * @param array $params
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function login(array $params): array
    {
        //获取用户信息
        $userInfo = Member::query()->where('address', $params['address'])->first();
        if (empty($userInfo)) {
            Member::query()->insert([
                    'address' => $params['address'],
                    'login_sign' => $params['login_sign'],
                    'username' => strtoupper(substr($params['address'], 0, 6)),
                    'status' => 1,
                    'chain_id' => $params['chain_id']
                ]
            );
            $userInfo = Member::query()->where('address', $params['address'])->first();
        }

        //关联商品
        Product::query()->where(['owner' => $params['address'], 'owner_id' => 0])->update(['owner_id' => $userInfo->id]);

        if ($userInfo->status == 0)
            $this->throwExp(400, __('validation.user_disabled'));

        $userData = [
            'uid' => $userInfo->id,
            'username' => $userInfo->username,
        ];
        $token = $this->jwt->getToken($userData);

        $responseData = $this->respondWithToken($token);

        //记录登陆日志
        $loginLogData = LoginLogService::getInstance()->collectLoginLogInfo();
        $loginLogData['response_code'] = 200;
        $loginLogData['response_result'] = __('validation.login_success');
        LoginLog::add($loginLogData);
        Cache::getInstance()->set('hyperf-api_' . $userInfo->id, $token);
        return $responseData;
    }

    /**
     * 处理注册逻辑
     * @param array $params
     * @return array
     */
    public function register(array $params): bool
    {
        //校验验证码 若是测试环境跳过验证码验证
        if (!env('APP_TEST')) {
            $container = ApplicationContext::getContainer();
            $redis = $container->get(\Hyperf\Redis\Redis::class);
            $code = $redis->get($params['code_key']);
            if (strtolower($params['captcha']) != strtolower($code)) $this->throwExp(StatusCode::ERR_CODE, '验证失败，验证码错误');
        }
        $postData = $this->request->all();

        $user = new User();
        $user->username = $postData['username'];
        $user->password = md5($postData['password']);
        $user->status = User::STATUS_ON;
        $user->avatar = 'https://shmily-album.oss-cn-shenzhen.aliyuncs.com/admin_face/face' . rand(1, 10) . '.png';
        $user->last_login = time();
        $user->last_ip = getClientIp($this->request);
        $user->creater = '无';
        $user->desc = $postData['desc'] ?? '';
        $user->sex = User::SEX_BY_Female;

        if (!$user->save()) $this->throwExp(StatusCode::ERR_EXCEPTION, '注册用户失败');
        $user->assignRole('tourist_admin');
        return true;
    }

    /**
     * 登陆初始化，获取用户信息以及一些权限菜单
     * @return mixed
     */
    public function initialization(): array
    {
        $responseData = [];
        //获取用户信息
        $user = UserService::getInstance()->getUserInfoByToken();
        $userInfo = objToArray($user);
        unset($userInfo['roles']);
        unset($userInfo['permissions']);

        $menu = $this->getMenuList($user);
        $responseData['user_info'] = objToArray($userInfo);
        $responseData['role_info'] = $user->getRoleNames();
        $responseData['menu_header'] = $menu['menuHeader'];
        $responseData['menu_list'] = $menu['menuList'];
        $responseData['permission'] = $menu['permission'];
        $responseData['permission_info'] = $menu['permission_info'];

        return $responseData;
    }

    /**
     * 处理权限得到路由（提供给前端注册路由）
     * @return array
     */
    public function getRouters(): array
    {
        $userInfo = conGet('user_info');
        $permissionList = Permission::getUserPermissions($userInfo);
        $permissionList = objToArray($permissionList);
        $permissionList = array_column($permissionList, null, 'id');

        foreach ($permissionList as $key => $val) {
            if ($val['status'] == Permission::OFF_STATUS) unset($permissionList[$key]);
            if ($val['type'] == Permission::BUTTON_OR_API_TYPE) unset($permissionList[$key]);
        }

        //使用引用传递递归数组
        $routers = [
            'default' => [
                'path' => '',
                'component' => 'Layout',
                'redirect' => '/home',
                'children' => [],
            ]
        ];
        $module_children = [];
        foreach ($permissionList as $key => $value) {
            if (isset($permissionList[$value['parent_id']])) {
                $permissionList[$value['parent_id']]['children'][] = &$permissionList[$key];
            } else {
                $module_children[] = &$permissionList[$key];
            }
        }
        foreach ($module_children as $key => $value) {
            if (!empty($value['children'])) {
                $routers[$value['id']] = [
                    'name' => $value['name'],
                    'path' => $value['url'],
                    'redirect' => 'noRedirect',
                    'hidden' => $value['hidden'],
                    'alwaysShow' => true,
                    'component' => $value['component'],
                    'meta' => [
                        'icon' => $value['icon'],
                        'title' => $value['display_name'],
                    ],
                    'children' => []
                ];
                $routers[$value['id']]['children'] = $this->dealRouteChildren($value['children']);
            } else {
                array_push($routers['default']['children'], [
                    'name' => $value['name'],
                    'path' => $value['url'],
                    'hidden' => $value['hidden'],
                    'alwaysShow' => true,
                    'component' => $value['component'],
                    'meta' => [
                        'icon' => $value['icon'],
                        'title' => $value['display_name'],
                    ],
                ]);
            }
        }
        return array_values($routers);
    }

    /**
     * 处理路由下顶级路由下子路由
     * @param array $children
     * @return array
     */
    private function dealRouteChildren(array $children): array
    {
        $temp = [];
        if (!empty($children)) {
            foreach ($children as $k => $v) {
                if ($v['type'] == Permission::MENU_TYPE) {
                    $temp[] = [
                        'name' => $v['name'],
                        'path' => $v['url'],
                        'hidden' => $v['hidden'],
                        'alwaysShow' => true,
                        'component' => $v['component'],
                        'meta' => [
                            'icon' => $v['icon'],
                            'title' => $v['display_name'],
                        ],
                    ];
                }
                if (!empty($v['children'])) {
                    $temp = array_merge($temp, $this->dealRouteChildren($v['children']));
                }
            }
        }
        return $temp;
    }

    /**
     * 处理TOKEN数据
     * @param $token
     * @return array
     */
    protected function respondWithToken(string $token): array
    {
        $data = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->jwt->getTTL(),
        ];
        return $data;
    }

    /**
     * 获取头部菜单数据以及菜单列表
     * @param object $user
     * @return array
     */
    protected function getMenuList(object $user): array
    {
        //获取菜单树形
        $menuList = Permission::getUserMenuList($user);
        $permission = Permission::getUserPermissions($user);
        $menuHeader = [];
        foreach ($menuList as $key => $val) {
            if ($val['status'] != 0) {
                $menuHeader[] = [
                    'title' => $val['display_name'],
                    'icon' => $val['icon'],
                    'path' => $val['url'],
                    'name' => $val['name'],
                    'id' => $val['id'],
                    'type' => $val['type'],
                    'sort' => $val['sort'],
                ];
            }
        }
        //排序
        array_multisort(array_column($menuHeader, 'sort'), SORT_ASC, $menuHeader);

        return [
            'menuList' => $menuList,
            'menuHeader' => $menuHeader,
            'permission' => array_column($permission, 'name'),
            'permission_info' => $permission,
        ];
    }


}
