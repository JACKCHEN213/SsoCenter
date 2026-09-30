<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// Index 控制器路由
use think\facade\Route;
use app\controller\OperationLog;
use app\http\middleware\CheckLogin;

Route::group('/', function () {
    Route::get('/', 'index');
    Route::get('index', 'index');
    Route::get('about', function () {
        return view('/about');
    });
    Route::get('404', function () {
        return view('/404');
    });
})->prefix('index/');

// 操作日志路由
Route::group('operation_log', function () {
    Route::get('', [OperationLog::class, 'index']);
    Route::post('list', [OperationLog::class, 'list']);
    Route::post('detail', [OperationLog::class, 'detail']);
});

// 会话管理路由
Route::group('session', function () {
    Route::get('', [app\controller\Session::class, 'index']);
    Route::post('list', [app\controller\Session::class, 'list']);
    Route::post('detail', [app\controller\Session::class, 'detail']);
    Route::post('logout', [app\controller\Session::class, 'logout']);
})->middleware(CheckLogin::class);
