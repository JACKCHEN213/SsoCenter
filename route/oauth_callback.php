<?php
declare(strict_types=1);

// OAuth 回调与登出通知端点路由
use app\controller\OAuthCallback;
use think\facade\Route;

Route::group('oauth', function () {
    // 登出通知（Bearer Token 注销会话）
    Route::post('logout', [OAuthCallback::class, 'logout']);
    // 认证回调（接收授权码和 state，重定向）
    Route::get('callback', [OAuthCallback::class, 'callback']);
});
