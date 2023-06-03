<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\facade\Route;

Route::group('app', function () {
    Route::post('add', 'add');
    Route::delete('delete', 'delete');
    Route::put('update', 'update');
    Route::post('upload_image', 'uploadImage');
    Route::delete('delete_uploaded_image', 'deleteUploadedImage');
    Route::get('download', 'download');
})->prefix('application/');

Route::group('/', function () {
    Route::get('/', 'index');
    Route::get('index', 'index');
    Route::get('orders', function () {
        return view('/orders');
    });
    Route::get('docs', function () {
        return view('/docs');
    });
    Route::get('charts', function () {
        return view('/charts');
    });
    Route::get('help', function () {
        return view('/help');
    });
    Route::get('download', function () {
        return view('/download');
    });
    Route::get('about', function () {
        return view('/about');
    });
    Route::get('404', function () {
        return view('/404');
    });
})->prefix('index/');

Route::group('login', function () {
    Route::get('/', 'index');
    Route::post('login', 'login');
    Route::post('verify', 'verify');
})->prefix('login/');

Route::group('reset_password', function () {
    Route::get('/', 'index');
})->prefix('reset_password/');

Route::group('signup', function () {
    Route::get('/', 'index');
    Route::post('/signup', 'signup');
})->prefix('signup/');

Route::group('system_manage', function () {
    Route::get('/settings', 'settings');
    Route::get('/account', 'account');
    Route::get('/notifications', 'notifications');
})->prefix('system_manage/');

Route::group('user', function () {
    Route::get('/', 'index');
    Route::post('/', 'add');
    Route::patch('/', 'update');
    Route::delete('/', 'delete');
})->prefix('user/');
