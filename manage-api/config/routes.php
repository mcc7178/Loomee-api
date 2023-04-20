<?php

declare(strict_types=1);

/**
 * 路由控制中心
 */

use Hyperf\HttpServer\Router\Router;
use App\Middleware\WsMiddleware;
use App\Middleware\RequestMiddleware;
use App\Middleware\PermissionMiddleware;


Router::get('/sweewee', function () {
    return 'Hello Hyperf.';
});

// Router::addServer('ws', function () {
//     Router::get('/', 'App\Controller\Laboratory\Ws\WebsocketController', [
//         'middleware' => [WsMiddleware::class]
//     ]);
// });


Router::addGroup('/lmadmin/', function () {
    // 用户模块
    Router::get('user',         'App\Controller\Loomee\UserController@index');
    Router::put('user/{id}',    'App\Controller\Loomee\UserController@updateStatus');
    // Router::get('update','App\Controller\UserController@update');
    // Router::post('delete','App\Controller\UserController@delete');


    // 合集模块
    Router::get('collection',                   'App\Controller\Loomee\CollectionController@index');
    Router::get('collection/list',              'App\Controller\Loomee\CollectionController@getList');
    Router::put('collection/status/{id}',       'App\Controller\Loomee\CollectionController@setStatus');
    Router::put('collection/copyright/{id}',    'App\Controller\Loomee\CollectionController@setCopyright');
    Router::put('collection/element/{id}',      'App\Controller\Loomee\CollectionController@setElement');
    Router::put('collection/{id}',              'App\Controller\Loomee\CollectionController@updateData');
    Router::post('collection',                  'App\Controller\Loomee\CollectionController@store');
    Router::put('collection/ad/{id}',           'App\Controller\Loomee\CollectionController@setAd');
    Router::put('collection/recommend/{id}',    'App\Controller\Loomee\CollectionController@setRecommend');

    // 合集分类
    Router::get('collection/cate',          'App\Controller\Loomee\CollecCateController@index');
    Router::get('collection/cate/{id}',     'App\Controller\Loomee\CollecCateController@getOne');
    Router::put('collection/cate/{id}',     'App\Controller\Loomee\CollecCateController@updateData');
    Router::delete('collection/cate/{id}',  'App\Controller\Loomee\CollecCateController@destroy');
    Router::post('collection/cate',         'App\Controller\Loomee\CollecCateController@store');

    // 合集交易额
    Router::get('collection/trade',         'App\Controller\Loomee\CollecTradeController@index');


    // NFT 管理
    Router::get('ntf',                  'App\Controller\Loomee\NftController@index');
    Router::get('ntf/element/{id}',     'App\Controller\Loomee\NftController@element');
    Router::post('ntf/getnft',          'App\Controller\Loomee\NftController@shelves');
    Router::get('ntf/dynamics',         'App\Controller\Loomee\NftController@dynamics');
    Router::get('ntf/dynamics/event',   'App\Controller\Loomee\NftController@getEvent');
    Router::get('user/follow',          'App\Controller\Loomee\UserController@follow');


    // 交易币种
    Router::get('coin',                  'App\Controller\Loomee\CoinController@index');
    Router::put('coin/{id}',             'App\Controller\Loomee\CoinController@update');
    Router::post('coin',                 'App\Controller\Loomee\CoinController@add');


    // 链
    Router::get('chain',        'App\Controller\Loomee\OtherController@index');
    Router::get('chain/{id}',   'App\Controller\Loomee\OtherController@chainGetOne');
    Router::put('chain/{id}',   'App\Controller\Loomee\OtherController@chainUpdate');
    Router::post('chain',       'App\Controller\Loomee\OtherController@chainAdd');


    // 平台信息
    Router::get('platform',        'App\Controller\Loomee\PlatformController@index');
    Router::put('platform/{id}',   'App\Controller\Loomee\PlatformController@update');



    // 首页基础设置
    Router::post('home/basic',             'App\Controller\Setting\HomeController@basic');
    Router::put('home/basicEdit/{id}',   'App\Controller\Setting\HomeController@basicEdit');
    // 首页第二屏和第四屏设置
    Router::post('home/index/{belong_page}',            'App\Controller\Setting\HomeController@index');
    Router::post('home/add/{belong_page}',               'App\Controller\Setting\HomeController@add');
    Router::addRoute(['get','post'],'home/edit/{belong_page}/{id}',          'App\Controller\Setting\HomeController@edit');
    //设置是否显示
    Router::post('home/setShow/{belong_page}/{id}',               'App\Controller\Setting\HomeController@setShow');   


    // 清理接口缓存
    Router::post('clear/cache',             'App\Controller\Common\ClearCacheController@clear');
    

},['middleware' => [RequestMiddleware::class,PermissionMiddleware::class]]);


Router::get('/hytest', 'App\Controller\Loomee\TestController@index');
Router::get('/read', 'App\Controller\Loomee\TestController@getDl');
