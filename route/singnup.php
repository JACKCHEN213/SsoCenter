<?php

use think\facade\Route;

Route::group('signup', function () {
    Route::get('/', 'index');
})->prefix('signup/');
