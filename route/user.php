<?php
declare(strict_types=1);

// User 控制器路由
use app\http\middleware\CheckLogin;
use app\http\middleware\ValidateParams;
use think\facade\Route;

Route::group('user', function () {
    Route::get('/', 'index');
    Route::post('/', 'add');
    Route::patch('/', 'update');
    Route::delete('/', 'delete');
    Route::post('/batch', 'batchAdd');
    Route::post('/migrate_passwords', 'migratePasswords');
    // T3.6 推送管理路由
    Route::post('/push_status', 'pushStatus')->middleware([CheckLogin::class, ValidateParams::class]);
    Route::post('/push', 'pushUser')->middleware([CheckLogin::class, ValidateParams::class]);
    Route::post('/push_all', 'pushUserToAll')->middleware([CheckLogin::class, ValidateParams::class]);
    Route::post('/push_log', 'pushLog')->middleware([CheckLogin::class, ValidateParams::class]);
})->prefix('user/');
