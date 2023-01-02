<?php

use think\facade\Route;

Route::group('login', function () {
    Route::get('/', 'index');
})->prefix('login/');
