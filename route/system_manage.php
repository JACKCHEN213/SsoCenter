<?php

use think\facade\Route;

Route::group('/system_manage/', function () {
    Route::get('settings', 'settings');
    Route::get('account', 'account');
    Route::get('notifications', 'notifications');
})->prefix('system_manage/');
