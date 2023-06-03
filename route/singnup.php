<?php

use think\facade\Route;

Route::group('signup', function () {
    Route::get('/', 'index');
    Route::post('/signup', 'signup');
})->prefix('signup/');
