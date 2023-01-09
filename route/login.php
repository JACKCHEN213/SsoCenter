<?php

use think\facade\Route;

Route::group('login', function () {
    Route::get('/', 'index');
    Route::post('login', 'login');
})->prefix('login/');
