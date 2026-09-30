<?php
declare(strict_types=1);

// Login 控制器路由
use think\facade\Route;

Route::group('login', function () {
    Route::get('/', 'index');
    Route::post('login', 'login');
    Route::post('verify', 'verify');
})->prefix('login/');
