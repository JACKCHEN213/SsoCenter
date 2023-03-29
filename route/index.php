<?php

use think\facade\Route;

Route::group('/', function () {
    Route::get('/', 'index');
    Route::get('index', 'index');
    Route::get('orders', function () {
        return view('/orders');
    });
    Route::get('docs', function () {
        return view('/docs');
    });
    Route::get('charts', function () {
        return view('/charts');
    });
    Route::get('help', function () {
        return view('/help');
    });
    Route::get('download', function () {
        return view('/download');
    });
    Route::get('about', function () {
        return view('/about');
    });
    Route::get('404', function () {
        return view('/404');
    });
})->prefix('index/');
