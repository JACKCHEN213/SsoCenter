<?php

use think\facade\Route;

Route::group('/', function () {
    Route::get('/', 'index');
    Route::get('index', 'index');
    Route::get('settings', 'settings');
    Route::get('account', 'account');
})->prefix('index/');
