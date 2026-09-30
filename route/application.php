<?php
declare(strict_types=1);

// Application 控制器路由
use app\http\middleware\CheckLogin;
use app\http\middleware\ValidateParams;
use think\facade\Route;

Route::group('app', function () {
    Route::post('add', 'add');
    Route::delete('delete', 'delete');
    Route::put('update', 'update');
    Route::post('upload_image', 'uploadImage');
    Route::delete('delete_uploaded_image', 'deleteUploadedImage');
    Route::get('download', 'download');
    // OAuth 应用详情 / 凭证管理（T2.8）
    Route::get('detail', 'detail')->middleware(ValidateParams::class);
    Route::post('reset_secret', 'resetSecret')->middleware([CheckLogin::class, ValidateParams::class]);
    Route::post('get_secret', 'getSecret')->middleware([CheckLogin::class, ValidateParams::class]);
    Route::post('download_secret', 'downloadSecret')->middleware([CheckLogin::class, ValidateParams::class]);
    Route::post('visit', 'visitApp')->middleware([CheckLogin::class, ValidateParams::class]);
    Route::post('visit_log', 'visitLog')->middleware([CheckLogin::class, ValidateParams::class]);
    Route::get('push_apis', 'getPushApis')->middleware(ValidateParams::class);
})->prefix('application/');
