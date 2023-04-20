<?php
declare(strict_types=1);

/**
 * 路由控制中心
 */

use App\Controller\CollectionController;
use App\Controller\PlatformController;
use Hyperf\HttpServer\Router\Router;
use App\Middleware\WsMiddleware;
use App\Middleware\RequestMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Controller\Auth\LoginController;
use \App\Controller\Common\UploadController;
use \App\Controller\Auth\UserController;
use \App\Controller\NFTController;
use \App\Controller\HomeController;


/** 客户端登录 */
Router::post('/api/auth/login', [LoginController::class, 'login']);

/** 客户端api接口 start */
Router::addGroup('/api/', function () {
    /***个人中心**/
    Router::post('user_edit/{id}', [UserController::class, 'userInfoEdit']);//修改用户信息
    Router::post('common/upload/single_pic', [UploadController::class, 'uploadSinglePic']);//上传图片
    //  Router::get('my_product', [NFTController::class, 'myProduct']);//已拥有的产品
    //  Router::get('my_product_detail', [NFTController::class, 'my_product_detail']);//个人中心-产品详情
    Router::post('update_product_status', [NFTController::class, 'updateProductStatus']);//商品上下架
    Router::post('follow', [NFTController::class, 'follow']);//商品关注
    Router::post('sign', [NFTController::class, 'sign']);//签名
    Router::post('buy', [NFTController::class, 'buy']);//下单

}, ['middleware' => [RequestMiddleware::class, PermissionMiddleware::class]]);

Router::addGroup('/api/', function () {
    Router::post('home/index', [HomeController::class, 'index']);//首页
    Router::get('options', [NFTController::class, 'getOptions']);//筛选项
    Router::get('product_list', [NFTController::class, 'list']);//产品列表
    Router::get('ranking_list', [NFTController::class, 'ranking_list']);//产品排行榜
    Router::get('collection_list', [CollectionController::class, 'list']);//合集列表
    Router::get('collection_dynamic', [CollectionController::class, 'dynamic']);//合集动态
    Router::get('collection_detail', [CollectionController::class, 'info']);//合集详情
    Router::post('callback', [NFTController::class, 'callback']);//购买回调
    Router::get('platform_info', [PlatformController::class, 'info']);//平台信息
    Router::get('binance_price', [NFTController::class, 'getBinancePrice']);//平台信息
    Router::post('refresh', [NFTController::class, 'refresh']);//刷新NFT数据
    Router::get('my_product', [NFTController::class, 'myProduct']);//已拥有的产品
    Router::get('my_product_detail', [NFTController::class, 'my_product_detail']);//个人中心-产品详情
    Router::post('user_info', [UserController::class, 'userInfo']);//用户信息
    Router::get('my_dynamic', [CollectionController::class, 'my_dynamic']);//我的动态
    Router::get('my_follow', [NFTController::class, 'myFollow']);//我的关注产品
});

Router::addGroup('/api/', function () {
    Router::get('product_detail', [NFTController::class, 'detail']);//产品详情
}, ['middleware' => [\App\Middleware\RequestWeakValidateMiddleware::class]]);

Router::addServer('ws', function () {
    Router::get('/', 'App\Controller\Laboratory\Ws\WebsocketController', [
        'middleware' => [WsMiddleware::class]
    ]);
});
Router::get("/test11/{id}/{name}", [\App\Controller\TestController::class, 'index']);
Router::post('/api/encrypt_by_public', [NFTController::class, 'encrypt_by_public']);//加密备用
Router::post('/api/decrypt_by_private', [NFTController::class, 'decrypt_by_private']);//解密备用