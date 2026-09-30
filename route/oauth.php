<?php
declare(strict_types=1);

// OAuth 2.0 端点路由
use app\controller\OAuth;
use app\http\middleware\OAuthClientAuth;
use think\facade\Route;

Route::group('oauth', function () {
    // 授权端点（授权码 + 隐式模式），基于 Cookie Session 检测登录态
    // Route::get('authorize', [OAuth::class, 'authorize']);
    // Token 端点（四种 grant_type），Basic Auth 客户端认证
    Route::post('token', [OAuth::class, 'token'])->middleware(OAuthClientAuth::class);
    // 获取用户信息（Body 中携带 access_token）
    Route::post('userinfo', [OAuth::class, 'userinfo']);
    // 应用通知登出（Basic Auth + access_token）
    Route::post('logout', [OAuth::class, 'logout'])->middleware(OAuthClientAuth::class);
    // 第三方系统回调登出（Basic Auth 客户端认证）
    Route::post('logout_callback', [OAuth::class, 'logoutCallback'])->middleware(OAuthClientAuth::class);
});

// SSO 授权登录页（页面路由，非 API，T4.7）
Route::get('oauth/authorize', [OAuth::class, 'authorizePage']);
Route::post('oauth/authorize/confirm', [OAuth::class, 'confirmAuthorize']);
Route::post('oauth/authorize/deny', [OAuth::class, 'denyAuthorize']);
