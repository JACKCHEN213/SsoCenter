<?php

use think\facade\Route;

Route::group('reset_password', function () {
    Route::get('/', 'index');
})->prefix('reset_password/');
