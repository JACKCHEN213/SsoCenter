<?php

use think\facade\Route;

Route::group('app', function () {
    Route::post('add', 'add');
    Route::delete('delete', 'delete');
    Route::put('update', 'update');
    Route::post('upload_image', 'uploadImage');
    Route::delete('delete_uploaded_image', 'deleteUploadedImage');
})->prefix('application/');
