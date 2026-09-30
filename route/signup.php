<?php
declare(strict_types=1);

// Signup 控制器路由
use think\facade\Route;

Route::group('signup', function () {
    Route::get('/', 'index');
    Route::post('/signup', 'signup');
})->prefix('signup/');
