<?php

use think\facade\Route;

Route::group('app', function () {
    Route::rule('upload_image', 'uploadImage');
})->method('post')->prefix('application/');
