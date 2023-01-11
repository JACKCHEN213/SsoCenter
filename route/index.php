<?php

use think\facade\Route;

Route::group('/', function () {
    Route::get('/', 'index');
    Route::get('index', 'index');
    Route::get('settings', 'settings');
    Route::get('account', 'account');
    Route::get('404', function () {
        return view('/404');
    });
})->prefix('index/');
