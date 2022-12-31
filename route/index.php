<?php

use think\facade\Route;

Route::group(['method' => 'get'], function () {
    Route::rule('/', 'index/index')->append(['url' => '/']);
    Route::rule('/:url', 'index/:url');
});
