<?php
declare(strict_types=1);

// ResetPassword 控制器路由
use think\facade\Route;

Route::group('reset_password', function () {
    Route::get('/', 'index');
})->prefix('reset_password/');
